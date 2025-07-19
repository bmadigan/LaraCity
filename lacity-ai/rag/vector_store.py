"""
Vector Store Manager for RAG System

Educational Focus:
- Vector store concepts and operations
- FAISS integration patterns
- Document indexing and persistence
- Vector search optimization
- Production vector store management
"""

import os
import pickle
from typing import List, Dict, Any, Optional, Tuple
from pathlib import Path
import numpy as np
import structlog

try:
    import faiss
    FAISS_AVAILABLE = True
except ImportError:
    FAISS_AVAILABLE = False
    faiss = None

from langchain.schema import Document
from langchain.vectorstores import FAISS as LangChainFAISS

from ..models.embeddings import EmbeddingGenerator
from ..config import config
from .document_loader import ComplaintDocumentLoader

logger = structlog.get_logger(__name__)


class VectorStoreManager:
    """
    Manages vector store operations for complaint documents
    
    Features:
    - FAISS vector store creation and management
    - Document indexing with embeddings
    - Vector search and retrieval
    - Persistence and loading operations
    - Metadata filtering capabilities
    
    Educational Value:
    - Vector database concepts
    - Embedding storage and retrieval
    - Search optimization techniques
    - Production vector store patterns
    """
    
    def __init__(self, vector_store_path: Optional[str] = None):
        """
        Initialize vector store manager
        
        Args:
            vector_store_path: Path to save/load vector store (defaults to config)
        """
        # Initialize components
        self.embedding_generator = EmbeddingGenerator()
        self.document_loader = ComplaintDocumentLoader()
        
        # Vector store configuration
        self.vector_store_path = Path(vector_store_path or config.VECTOR_STORE_PATH)
        self.vector_store: Optional[LangChainFAISS] = None
        self.documents: List[Document] = []
        
        # Ensure vector store directory exists
        self.vector_store_path.parent.mkdir(parents=True, exist_ok=True)
        
        # Check FAISS availability
        if not FAISS_AVAILABLE:
            logger.warning("FAISS not available, vector operations will be limited")
        
        logger.info("VectorStoreManager initialized",
                   vector_store_path=str(self.vector_store_path),
                   faiss_available=FAISS_AVAILABLE)
    
    def create_vector_store_from_documents(self, documents: List[Document]) -> bool:
        """
        Create vector store from documents
        
        Educational Focus:
        - Document embedding process
        - Vector store initialization
        - Batch processing for efficiency
        - Error handling in indexing
        
        Args:
            documents: List of LangChain Documents to index
            
        Returns:
            True if successful, False otherwise
        """
        if not documents:
            logger.warning("No documents provided for vector store creation")
            return False
        
        if not FAISS_AVAILABLE:
            logger.error("FAISS not available, cannot create vector store")
            return False
        
        logger.info("Creating vector store from documents",
                   document_count=len(documents))
        
        try:
            # Extract text content for embedding
            texts = [doc.page_content for doc in documents]
            metadatas = [doc.metadata for doc in documents]
            
            # Create embeddings for all documents
            logger.info("Generating embeddings for documents")
            embeddings = []
            
            # Process in batches to manage memory
            batch_size = config.EMBEDDING_BATCH_SIZE
            for i in range(0, len(texts), batch_size):
                batch_texts = texts[i:i + batch_size]
                batch_embeddings = self.embedding_generator.embed_documents(batch_texts)
                embeddings.extend(batch_embeddings)
                
                logger.debug("Processed embedding batch",
                           batch_number=i // batch_size + 1,
                           batch_size=len(batch_texts),
                           total_processed=len(embeddings))
            
            # Create FAISS vector store
            logger.info("Creating FAISS vector store")
            self.vector_store = LangChainFAISS.from_embeddings(
                text_embeddings=list(zip(texts, embeddings)),
                embedding=self.embedding_generator,
                metadatas=metadatas
            )
            
            # Store documents for reference
            self.documents = documents.copy()
            
            logger.info("Vector store created successfully",
                       document_count=len(documents),
                       embedding_dimension=len(embeddings[0]) if embeddings else 0)
            
            return True
            
        except Exception as e:
            logger.error("Failed to create vector store",
                        document_count=len(documents),
                        error=str(e))
            return False
    
    def create_vector_store_from_complaints(self, 
                                          complaint_data: List[Dict[str, Any]],
                                          chunk_documents: bool = True) -> bool:
        """
        Create vector store directly from complaint data
        
        Educational Focus:
        - End-to-end RAG pipeline
        - Document processing options
        - Performance considerations
        
        Args:
            complaint_data: List of complaint dictionaries
            chunk_documents: Whether to chunk large documents
            
        Returns:
            True if successful, False otherwise
        """
        if not complaint_data:
            logger.warning("No complaint data provided")
            return False
        
        logger.info("Creating vector store from complaint data",
                   complaint_count=len(complaint_data),
                   chunk_documents=chunk_documents)
        
        try:
            # Convert complaints to documents
            if chunk_documents:
                documents = self.document_loader.load_and_chunk_complaints(complaint_data)
            else:
                documents = self.document_loader.load_complaints_as_documents(complaint_data)
            
            if not documents:
                logger.error("No documents created from complaint data")
                return False
            
            # Create vector store from documents
            return self.create_vector_store_from_documents(documents)
            
        except Exception as e:
            logger.error("Failed to create vector store from complaints",
                        complaint_count=len(complaint_data),
                        error=str(e))
            return False
    
    def add_documents_to_store(self, documents: List[Document]) -> bool:
        """
        Add new documents to existing vector store
        
        Educational Focus:
        - Incremental indexing patterns
        - Vector store updates
        - Consistency management
        """
        if not documents:
            logger.warning("No documents provided to add")
            return False
        
        if not self.vector_store:
            logger.warning("No vector store exists, creating new one")
            return self.create_vector_store_from_documents(documents)
        
        logger.info("Adding documents to existing vector store",
                   new_document_count=len(documents),
                   existing_document_count=len(self.documents))
        
        try:
            # Extract texts and metadata
            texts = [doc.page_content for doc in documents]
            metadatas = [doc.metadata for doc in documents]
            
            # Add to vector store
            self.vector_store.add_texts(texts, metadatas)
            
            # Update document collection
            self.documents.extend(documents)
            
            logger.info("Documents added successfully",
                       total_documents=len(self.documents))
            
            return True
            
        except Exception as e:
            logger.error("Failed to add documents to vector store",
                        new_document_count=len(documents),
                        error=str(e))
            return False
    
    def search_similar_documents(self, 
                               query: str,
                               k: int = 5,
                               score_threshold: float = 0.0,
                               filters: Optional[Dict[str, Any]] = None) -> List[Tuple[Document, float]]:
        """
        Search for similar documents using vector similarity
        
        Educational Focus:
        - Vector similarity search concepts
        - Retrieval parameters and tuning
        - Metadata filtering integration
        - Score interpretation
        
        Args:
            query: Search query text
            k: Number of results to return
            score_threshold: Minimum similarity score
            filters: Metadata filters to apply
            
        Returns:
            List of (document, score) tuples
        """
        if not self.vector_store:
            logger.warning("No vector store available for search")
            return []
        
        if not query or not query.strip():
            logger.warning("Empty query provided")
            return []
        
        logger.info("Searching for similar documents",
                   query_length=len(query),
                   k=k,
                   score_threshold=score_threshold,
                   has_filters=filters is not None)
        
        try:
            # Perform similarity search with scores
            results = self.vector_store.similarity_search_with_score(
                query=query.strip(),
                k=k,
                filter=filters
            )
            
            # Filter by score threshold
            filtered_results = [
                (doc, score) for doc, score in results 
                if score >= score_threshold
            ]
            
            logger.info("Similar documents found",
                       total_results=len(results),
                       filtered_results=len(filtered_results),
                       best_score=max([score for _, score in filtered_results]) if filtered_results else 0)
            
            return filtered_results
            
        except Exception as e:
            logger.error("Failed to search similar documents",
                        query=query[:100],
                        error=str(e))
            return []
    
    def search_by_embedding(self,
                          embedding: List[float],
                          k: int = 5,
                          score_threshold: float = 0.0) -> List[Tuple[Document, float]]:
        """
        Search using pre-computed embedding
        
        Educational Focus:
        - Direct embedding search
        - Performance optimization
        - Use cases for pre-computed embeddings
        """
        if not self.vector_store:
            logger.warning("No vector store available for embedding search")
            return []
        
        if not embedding:
            logger.warning("Empty embedding provided")
            return []
        
        logger.debug("Searching by embedding",
                    embedding_dimension=len(embedding),
                    k=k)
        
        try:
            # Convert to numpy array for FAISS
            query_embedding = np.array([embedding], dtype=np.float32)
            
            # Perform search
            scores, indices = self.vector_store.index.search(query_embedding, k)
            
            # Convert results to documents
            results = []
            for i, (score, idx) in enumerate(zip(scores[0], indices[0])):
                if score >= score_threshold and idx < len(self.documents):
                    results.append((self.documents[idx], float(score)))
            
            logger.debug("Embedding search completed",
                        results_found=len(results))
            
            return results
            
        except Exception as e:
            logger.error("Failed to search by embedding",
                        embedding_dimension=len(embedding),
                        error=str(e))
            return []
    
    def save_vector_store(self, path: Optional[str] = None) -> bool:
        """
        Save vector store to disk
        
        Educational Focus:
        - Vector store persistence
        - File organization patterns
        - Error handling in I/O operations
        """
        if not self.vector_store:
            logger.warning("No vector store to save")
            return False
        
        save_path = Path(path) if path else self.vector_store_path
        save_path.parent.mkdir(parents=True, exist_ok=True)
        
        logger.info("Saving vector store",
                   path=str(save_path),
                   document_count=len(self.documents))
        
        try:
            # Save the FAISS vector store
            self.vector_store.save_local(str(save_path))
            
            # Save additional metadata
            metadata_path = save_path.with_suffix('.metadata.pkl')
            with open(metadata_path, 'wb') as f:
                pickle.dump({
                    'documents': self.documents,
                    'document_count': len(self.documents),
                    'embedding_dimension': config.EMBEDDING_DIMENSION,
                    'model_name': config.OPENAI_EMBEDDING_MODEL
                }, f)
            
            logger.info("Vector store saved successfully",
                       path=str(save_path),
                       metadata_path=str(metadata_path))
            
            return True
            
        except Exception as e:
            logger.error("Failed to save vector store",
                        path=str(save_path),
                        error=str(e))
            return False
    
    def load_vector_store(self, path: Optional[str] = None) -> bool:
        """
        Load vector store from disk
        
        Educational Focus:
        - Vector store loading patterns
        - Version compatibility checks
        - Recovery strategies
        """
        load_path = Path(path) if path else self.vector_store_path
        
        if not load_path.exists():
            logger.warning("Vector store path does not exist",
                          path=str(load_path))
            return False
        
        logger.info("Loading vector store",
                   path=str(load_path))
        
        try:
            # Load the FAISS vector store
            self.vector_store = LangChainFAISS.load_local(
                str(load_path),
                self.embedding_generator
            )
            
            # Load additional metadata if available
            metadata_path = load_path.with_suffix('.metadata.pkl')
            if metadata_path.exists():
                with open(metadata_path, 'rb') as f:
                    metadata = pickle.load(f)
                    self.documents = metadata.get('documents', [])
                    
                    logger.debug("Loaded vector store metadata",
                                document_count=metadata.get('document_count'),
                                embedding_dimension=metadata.get('embedding_dimension'),
                                model_name=metadata.get('model_name'))
            
            logger.info("Vector store loaded successfully",
                       path=str(load_path),
                       document_count=len(self.documents))
            
            return True
            
        except Exception as e:
            logger.error("Failed to load vector store",
                        path=str(load_path),
                        error=str(e))
            return False
    
    def get_store_stats(self) -> Dict[str, Any]:
        """Get statistics about the vector store"""
        if not self.vector_store:
            return {'exists': False}
        
        stats = {
            'exists': True,
            'document_count': len(self.documents),
            'vector_dimension': config.EMBEDDING_DIMENSION,
            'model_name': config.OPENAI_EMBEDDING_MODEL
        }
        
        # Add FAISS-specific stats if available
        if hasattr(self.vector_store, 'index'):
            stats.update({
                'index_size': self.vector_store.index.ntotal,
                'index_dimension': self.vector_store.index.d
            })
        
        # Analyze document metadata
        if self.documents:
            boroughs = set()
            agencies = set()
            complaint_types = set()
            
            for doc in self.documents:
                metadata = doc.metadata
                if metadata.get('borough'):
                    boroughs.add(metadata['borough'])
                if metadata.get('agency'):
                    agencies.add(metadata['agency'])
                if metadata.get('complaint_type'):
                    complaint_types.add(metadata['complaint_type'])
            
            stats.update({
                'unique_boroughs': len(boroughs),
                'unique_agencies': len(agencies),
                'unique_complaint_types': len(complaint_types),
                'borough_list': sorted(list(boroughs)),
                'agency_list': sorted(list(agencies)),
                'complaint_type_list': sorted(list(complaint_types))
            })
        
        return stats
    
    def delete_vector_store(self, path: Optional[str] = None) -> bool:
        """Delete vector store files"""
        delete_path = Path(path) if path else self.vector_store_path
        
        try:
            if delete_path.exists():
                # Delete FAISS files
                for file_path in delete_path.glob('*'):
                    file_path.unlink()
                delete_path.rmdir()
                
                # Delete metadata file
                metadata_path = delete_path.with_suffix('.metadata.pkl')
                if metadata_path.exists():
                    metadata_path.unlink()
                
                logger.info("Vector store deleted",
                           path=str(delete_path))
                
                # Clear in-memory store
                self.vector_store = None
                self.documents = []
                
                return True
        except Exception as e:
            logger.error("Failed to delete vector store",
                        path=str(delete_path),
                        error=str(e))
        
        return False
    
    def rebuild_index(self) -> bool:
        """Rebuild the vector index from existing documents"""
        if not self.documents:
            logger.warning("No documents available to rebuild index")
            return False
        
        logger.info("Rebuilding vector index",
                   document_count=len(self.documents))
        
        # Create new vector store from existing documents
        return self.create_vector_store_from_documents(self.documents)


# Global vector store manager instance
vector_store_manager = VectorStoreManager()