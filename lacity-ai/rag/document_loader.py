"""
Document Loader for NYC 311 Complaint Data

Educational Focus:
- Document loading and preprocessing patterns
- Structured data to document conversion
- Metadata management for retrieval
- Chunking strategies for large documents
"""

import json
from typing import List, Dict, Any, Optional, Iterator
from langchain.schema import Document
from langchain.text_splitter import RecursiveCharacterTextSplitter
import structlog

import sys
import os
# Add the parent directory to the path so we can import config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config import config

logger = structlog.get_logger(__name__)


class ComplaintDocumentLoader:
    """
    Loads and processes NYC 311 complaint data into LangChain Document format
    
    Features:
    - Converts structured complaint data to documents
    - Adds rich metadata for filtering and retrieval
    - Handles text chunking for large complaints
    - Supports batch processing for efficiency
    
    Educational Value:
    - Shows document loading patterns for structured data
    - Demonstrates metadata strategy for retrieval
    - Text preprocessing and chunking concepts
    """
    
    def __init__(self, chunk_size: Optional[int] = None, chunk_overlap: Optional[int] = None):
        """
        Initialize document loader
        
        Args:
            chunk_size: Size of text chunks (defaults to config)
            chunk_overlap: Overlap between chunks (defaults to config)
        """
        self.chunk_size = chunk_size or config.RAG_CHUNK_SIZE
        self.chunk_overlap = chunk_overlap or config.RAG_CHUNK_OVERLAP
        
        # Initialize text splitter for chunking
        self.text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=self.chunk_size,
            chunk_overlap=self.chunk_overlap,
            length_function=len,
            separators=["\n\n", "\n", ". ", " ", ""]
        )
        
        logger.info("ComplaintDocumentLoader initialized",
                   chunk_size=self.chunk_size,
                   chunk_overlap=self.chunk_overlap)
    
    def load_complaint_as_document(self, complaint_data: Dict[str, Any]) -> Document:
        """
        Convert single complaint to LangChain Document
        
        Args:
            complaint_data: Dictionary containing complaint information
            
        Returns:
            LangChain Document with content and metadata
        """
        # Create structured text content
        content = self._format_complaint_content(complaint_data)
        
        # Create comprehensive metadata
        metadata = self._create_complaint_metadata(complaint_data)
        
        # Create Document
        document = Document(
            page_content=content,
            metadata=metadata
        )
        
        logger.debug("Complaint converted to document",
                    complaint_id=complaint_data.get('id'),
                    content_length=len(content),
                    metadata_keys=list(metadata.keys()))
        
        return document
    
    def load_complaints_as_documents(self, complaints: List[Dict[str, Any]]) -> List[Document]:
        """
        Convert multiple complaints to documents
        
        Args:
            complaints: List of complaint dictionaries
            
        Returns:
            List of LangChain Documents
        """
        if not complaints:
            return []
        
        logger.info("Loading complaints as documents",
                   complaint_count=len(complaints))
        
        documents = []
        for complaint in complaints:
            try:
                document = self.load_complaint_as_document(complaint)
                documents.append(document)
            except Exception as e:
                logger.error("Failed to convert complaint to document",
                           complaint_id=complaint.get('id'),
                           error=str(e))
        
        logger.info("Complaints loaded as documents",
                   input_count=len(complaints),
                   output_count=len(documents))
        
        return documents
    
    def load_and_chunk_complaints(self, complaints: List[Dict[str, Any]]) -> List[Document]:
        """
        Load complaints and split into chunks for large content
        
        Educational Focus:
        - When and why to chunk documents
        - Maintaining context across chunks
        - Metadata preservation in chunks
        """
        documents = self.load_complaints_as_documents(complaints)
        
        if not documents:
            return []
        
        logger.info("Chunking complaint documents",
                   document_count=len(documents))
        
        # Split documents into chunks
        chunked_documents = []
        for doc in documents:
            # Check if document needs chunking
            if len(doc.page_content) > self.chunk_size:
                chunks = self.text_splitter.split_documents([doc])
                
                # Add chunk metadata
                for i, chunk in enumerate(chunks):
                    chunk.metadata.update({
                        'chunk_index': i,
                        'total_chunks': len(chunks),
                        'is_chunked': True
                    })
                
                chunked_documents.extend(chunks)
                
                logger.debug("Document chunked",
                           original_length=len(doc.page_content),
                           chunk_count=len(chunks))
            else:
                # Keep original document
                doc.metadata['is_chunked'] = False
                chunked_documents.append(doc)
        
        logger.info("Document chunking completed",
                   input_documents=len(documents),
                   output_chunks=len(chunked_documents))
        
        return chunked_documents
    
    def _format_complaint_content(self, complaint: Dict[str, Any]) -> str:
        """
        Format complaint data into structured text content
        
        Educational Focus:
        - Text formatting strategies for retrieval
        - Information hierarchy and structure
        - Balancing detail vs conciseness
        """
        # Core complaint information
        complaint_type = complaint.get('type', 'Unknown Type')
        description = complaint.get('description', 'No description provided')
        
        # Location information
        borough = complaint.get('borough', 'Unknown Borough')
        address = complaint.get('address', 'Address not specified')
        
        # Agency and administrative info
        agency = complaint.get('agency', 'Unknown Agency')
        agency_name = complaint.get('agency_name', '')
        status = complaint.get('status', 'Unknown Status')
        
        # Temporal information
        submitted_at = complaint.get('submitted_at', 'Unknown submission time')
        
        # Build structured content
        content_parts = [
            f"COMPLAINT TYPE: {complaint_type}",
            f"DESCRIPTION: {description}",
            f"LOCATION: {borough}, {address}",
            f"RESPONSIBLE AGENCY: {agency}" + (f" ({agency_name})" if agency_name else ""),
            f"STATUS: {status}",
            f"SUBMITTED: {submitted_at}"
        ]
        
        # Add analysis information if available
        if 'analysis' in complaint:
            analysis = complaint['analysis']
            if isinstance(analysis, dict):
                risk_score = analysis.get('risk_score')
                category = analysis.get('category')
                summary = analysis.get('summary')
                
                if risk_score is not None:
                    content_parts.append(f"RISK SCORE: {risk_score}")
                if category:
                    content_parts.append(f"CATEGORY: {category}")
                if summary:
                    content_parts.append(f"ANALYSIS: {summary}")
        
        # Add additional fields if present
        if complaint.get('priority'):
            content_parts.append(f"PRIORITY: {complaint['priority']}")
        
        if complaint.get('resolved_at'):
            content_parts.append(f"RESOLVED: {complaint['resolved_at']}")
        
        # Join all parts with newlines
        formatted_content = "\n".join(content_parts)
        
        return formatted_content
    
    def _create_complaint_metadata(self, complaint: Dict[str, Any]) -> Dict[str, Any]:
        """
        Create comprehensive metadata for complaint document
        
        Educational Focus:
        - Metadata strategy for effective retrieval
        - Filtering and faceting capabilities
        - Performance considerations
        """
        metadata = {
            # Core identifiers
            'complaint_id': str(complaint.get('id', '')),
            'complaint_number': str(complaint.get('complaint_number', '')),
            
            # Classification metadata
            'complaint_type': complaint.get('type', ''),
            'category': complaint.get('category', ''),
            'agency': complaint.get('agency', ''),
            'agency_name': complaint.get('agency_name', ''),
            
            # Location metadata
            'borough': complaint.get('borough', ''),
            'city': complaint.get('city', ''),
            'address': complaint.get('address', ''),
            'zip_code': complaint.get('incident_zip', ''),
            
            # Status and priority
            'status': complaint.get('status', ''),
            'priority': complaint.get('priority', ''),
            
            # Temporal metadata
            'submitted_at': complaint.get('submitted_at', ''),
            'resolved_at': complaint.get('resolved_at', ''),
            
            # Document type for filtering
            'document_type': 'complaint',
            'source': 'nyc_311'
        }
        
        # Add coordinates if available
        if complaint.get('latitude') and complaint.get('longitude'):
            metadata.update({
                'latitude': float(complaint['latitude']),
                'longitude': float(complaint['longitude']),
                'has_coordinates': True
            })
        else:
            metadata['has_coordinates'] = False
        
        # Add analysis metadata if available
        if 'analysis' in complaint and isinstance(complaint['analysis'], dict):
            analysis = complaint['analysis']
            metadata.update({
                'risk_score': analysis.get('risk_score'),
                'analysis_category': analysis.get('category', ''),
                'has_analysis': True
            })
            
            # Add risk level classification
            risk_score = analysis.get('risk_score')
            if risk_score is not None:
                metadata['risk_level'] = config.get_risk_level(risk_score)
        else:
            metadata['has_analysis'] = False
        
        # Clean up None values
        cleaned_metadata = {k: v for k, v in metadata.items() if v is not None}
        
        return cleaned_metadata
    
    def load_from_database_result(self, db_results: List[Dict[str, Any]]) -> List[Document]:
        """
        Load documents from database query results
        
        Educational Focus:
        - Database integration patterns
        - Result set processing
        - Error handling for real data
        """
        if not db_results:
            return []
        
        logger.info("Loading documents from database results",
                   result_count=len(db_results))
        
        documents = []
        for result in db_results:
            try:
                # Convert database result to complaint format
                complaint_data = self._normalize_db_result(result)
                
                # Create document
                document = self.load_complaint_as_document(complaint_data)
                documents.append(document)
                
            except Exception as e:
                logger.error("Failed to process database result",
                           result=result,
                           error=str(e))
        
        logger.info("Documents loaded from database",
                   input_results=len(db_results),
                   output_documents=len(documents))
        
        return documents
    
    def _normalize_db_result(self, result: Dict[str, Any]) -> Dict[str, Any]:
        """
        Normalize database result to standard complaint format
        
        This handles differences between database column names
        and the expected complaint data format
        """
        # Map database columns to expected format
        normalized = {
            'id': result.get('id'),
            'complaint_number': result.get('complaint_number'),
            'type': result.get('complaint_type'),
            'description': result.get('descriptor'),
            'agency': result.get('agency'),
            'agency_name': result.get('agency_name'),
            'borough': result.get('borough'),
            'city': result.get('city'),
            'address': result.get('incident_address'),
            'incident_zip': result.get('incident_zip'),
            'latitude': result.get('latitude'),
            'longitude': result.get('longitude'),
            'status': result.get('status'),
            'priority': result.get('priority'),
            'submitted_at': result.get('submitted_at'),
            'resolved_at': result.get('resolved_at')
        }
        
        # Add analysis data if present (from JOIN)
        if any(key.startswith('analysis_') for key in result.keys()):
            normalized['analysis'] = {
                'risk_score': result.get('analysis_risk_score'),
                'category': result.get('analysis_category'),
                'summary': result.get('analysis_summary'),
                'tags': result.get('analysis_tags')
            }
        
        # Clean up None values
        cleaned = {k: v for k, v in normalized.items() if v is not None}
        
        return cleaned
    
    def get_document_stats(self, documents: List[Document]) -> Dict[str, Any]:
        """Get statistics about loaded documents"""
        if not documents:
            return {'total_documents': 0}
        
        # Basic stats
        total_docs = len(documents)
        total_content_length = sum(len(doc.page_content) for doc in documents)
        avg_content_length = total_content_length / total_docs if total_docs > 0 else 0
        
        # Metadata analysis
        boroughs = set()
        agencies = set()
        complaint_types = set()
        chunked_docs = 0
        
        for doc in documents:
            metadata = doc.metadata
            
            if metadata.get('borough'):
                boroughs.add(metadata['borough'])
            if metadata.get('agency'):
                agencies.add(metadata['agency'])
            if metadata.get('complaint_type'):
                complaint_types.add(metadata['complaint_type'])
            if metadata.get('is_chunked'):
                chunked_docs += 1
        
        return {
            'total_documents': total_docs,
            'total_content_length': total_content_length,
            'average_content_length': avg_content_length,
            'chunked_documents': chunked_docs,
            'unique_boroughs': len(boroughs),
            'unique_agencies': len(agencies),
            'unique_complaint_types': len(complaint_types),
            'borough_list': sorted(list(boroughs)),
            'agency_list': sorted(list(agencies)),
            'complaint_type_list': sorted(list(complaint_types))
        }


# Global document loader instance
complaint_document_loader = ComplaintDocumentLoader()