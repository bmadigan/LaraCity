"""
Embedding Generation for LaraCity AI

Educational Focus:
- OpenAI embeddings API integration
- Batch processing for efficiency
- Vector similarity concepts
- Embedding storage patterns
"""

import numpy as np
from typing import List, Dict, Any, Optional, Union
import structlog
from langchain_openai import OpenAIEmbeddings
import sys
import os
# Add the parent directory to the path so we can import config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config import config

logger = structlog.get_logger(__name__)


class EmbeddingGenerator:
    """
    Handles embedding generation for complaint analysis and RAG system
    
    Features:
    - Batch processing for efficiency
    - Caching to avoid redundant API calls
    - Similarity calculations
    - Integration with vector stores
    """
    
    def __init__(self):
        """Initialize embedding generator with OpenAI client"""
        self.embeddings = OpenAIEmbeddings(
            api_key=config.OPENAI_API_KEY,
            model=config.OPENAI_EMBEDDING_MODEL,
            timeout=config.REQUEST_TIMEOUT,
        )
        
        logger.info("Embedding generator initialized",
                   model=config.OPENAI_EMBEDDING_MODEL,
                   dimension=config.EMBEDDING_DIMENSION)
    
    def embed_complaint(self, complaint_data: Dict[str, Any]) -> List[float]:
        """
        Generate embeddings for a single complaint
        
        Args:
            complaint_data: Dictionary containing complaint information
            
        Returns:
            Embedding vector as list of floats
        """
        # Create structured text representation of complaint
        complaint_text = self._format_complaint_for_embedding(complaint_data)
        
        logger.debug("Generating embedding for complaint",
                    complaint_id=complaint_data.get('id'),
                    text_length=len(complaint_text))
        
        try:
            embedding = self.embeddings.embed_query(complaint_text)
            
            logger.debug("Complaint embedding generated",
                        complaint_id=complaint_data.get('id'),
                        embedding_dimension=len(embedding))
            
            return embedding
            
        except Exception as e:
            logger.error("Failed to generate complaint embedding",
                        complaint_id=complaint_data.get('id'),
                        error=str(e))
            raise
    
    def embed_complaints_batch(self, 
                             complaints: List[Dict[str, Any]],
                             batch_size: int = 10) -> List[List[float]]:
        """
        Generate embeddings for multiple complaints efficiently
        
        Args:
            complaints: List of complaint dictionaries
            batch_size: Number of complaints to process per batch
            
        Returns:
            List of embedding vectors
        """
        if not complaints:
            return []
        
        logger.info("Generating embeddings for complaint batch",
                   complaint_count=len(complaints),
                   batch_size=batch_size)
        
        all_embeddings = []
        
        # Process in batches to manage API rate limits
        for i in range(0, len(complaints), batch_size):
            batch = complaints[i:i + batch_size]
            
            # Format all complaints in batch
            batch_texts = [
                self._format_complaint_for_embedding(complaint)
                for complaint in batch
            ]
            
            try:
                # Generate embeddings for entire batch
                batch_embeddings = self.embeddings.embed_documents(batch_texts)
                all_embeddings.extend(batch_embeddings)
                
                logger.debug("Batch embeddings generated",
                           batch_start=i,
                           batch_size=len(batch),
                           embeddings_generated=len(batch_embeddings))
                
            except Exception as e:
                logger.error("Failed to generate batch embeddings",
                           batch_start=i,
                           batch_size=len(batch),
                           error=str(e))
                raise
        
        logger.info("All complaint embeddings generated",
                   total_embeddings=len(all_embeddings))
        
        return all_embeddings
    
    def embed_user_question(self, question: str) -> List[float]:
        """
        Generate embedding for user question (for RAG similarity search)
        
        Args:
            question: User's natural language question
            
        Returns:
            Embedding vector
        """
        if not question or not question.strip():
            raise ValueError("Question cannot be empty")
        
        logger.debug("Generating embedding for user question",
                    question_length=len(question))
        
        try:
            embedding = self.embeddings.embed_query(question.strip())
            
            logger.debug("Question embedding generated",
                        embedding_dimension=len(embedding))
            
            return embedding
            
        except Exception as e:
            logger.error("Failed to generate question embedding",
                        question=question[:100],  # Log first 100 chars
                        error=str(e))
            raise
    
    def _format_complaint_for_embedding(self, complaint: Dict[str, Any]) -> str:
        """
        Format complaint data into structured text for embedding
        
        This creates a comprehensive text representation that captures
        all relevant information for semantic similarity
        """
        # Core complaint information
        complaint_type = complaint.get('type', 'Unknown')
        description = complaint.get('description', '')
        
        # Location information
        borough = complaint.get('borough', '')
        address = complaint.get('address', '')
        
        # Agency and status
        agency = complaint.get('agency', '')
        status = complaint.get('status', '')
        
        # Create structured text
        parts = [
            f"Complaint Type: {complaint_type}",
            f"Description: {description}",
            f"Location: {borough}, {address}",
            f"Responsible Agency: {agency}",
            f"Status: {status}"
        ]
        
        # Add additional context if available
        if complaint.get('submitted_at'):
            parts.append(f"Submitted: {complaint['submitted_at']}")
        
        # Join all parts into coherent text
        formatted_text = ". ".join(filter(None, parts))
        
        logger.debug("Formatted complaint for embedding",
                    original_fields=len(complaint),
                    formatted_length=len(formatted_text))
        
        return formatted_text
    
    def calculate_similarity(self, 
                           embedding1: List[float], 
                           embedding2: List[float]) -> float:
        """
        Calculate cosine similarity between two embeddings
        
        Args:
            embedding1: First embedding vector
            embedding2: Second embedding vector
            
        Returns:
            Similarity score between 0 and 1
        """
        if len(embedding1) != len(embedding2):
            raise ValueError("Embeddings must have the same dimension")
        
        # Convert to numpy arrays for efficient computation
        vec1 = np.array(embedding1)
        vec2 = np.array(embedding2)
        
        # Calculate cosine similarity
        dot_product = np.dot(vec1, vec2)
        norm1 = np.linalg.norm(vec1)
        norm2 = np.linalg.norm(vec2)
        
        if norm1 == 0 or norm2 == 0:
            return 0.0
        
        similarity = dot_product / (norm1 * norm2)
        
        # Ensure result is between 0 and 1
        similarity = max(0.0, min(1.0, (similarity + 1) / 2))
        
        return float(similarity)
    
    def find_similar_complaints(self,
                              query_embedding: List[float],
                              complaint_embeddings: List[List[float]],
                              complaint_ids: List[str],
                              top_k: int = 5,
                              threshold: float = 0.7) -> List[Dict[str, Any]]:
        """
        Find most similar complaints based on embedding similarity
        
        Args:
            query_embedding: Embedding of query/question
            complaint_embeddings: List of all complaint embeddings
            complaint_ids: List of complaint IDs (same order as embeddings)
            top_k: Number of results to return
            threshold: Minimum similarity threshold
            
        Returns:
            List of similar complaints with similarity scores
        """
        if len(complaint_embeddings) != len(complaint_ids):
            raise ValueError("Embeddings and IDs lists must have same length")
        
        similarities = []
        
        for i, complaint_embedding in enumerate(complaint_embeddings):
            similarity = self.calculate_similarity(query_embedding, complaint_embedding)
            
            if similarity >= threshold:
                similarities.append({
                    'complaint_id': complaint_ids[i],
                    'similarity': similarity,
                    'index': i
                })
        
        # Sort by similarity (highest first) and return top_k
        similarities.sort(key=lambda x: x['similarity'], reverse=True)
        
        results = similarities[:top_k]
        
        logger.info("Similar complaints found",
                   total_complaints=len(complaint_embeddings),
                   above_threshold=len(similarities),
                   returned=len(results),
                   threshold=threshold)
        
        return results
    
    def get_embedding_info(self) -> Dict[str, Any]:
        """Get information about embedding configuration"""
        return {
            "model": config.OPENAI_EMBEDDING_MODEL,
            "dimension": config.EMBEDDING_DIMENSION,
            "similarity_threshold": config.SIMILARITY_THRESHOLD,
            "vector_search_k": config.VECTOR_SEARCH_K
        }


# Global embedding generator instance
embedding_generator = EmbeddingGenerator()