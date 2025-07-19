"""
Complaint Retriever for RAG System

Educational Focus:
- Advanced retrieval strategies
- Multi-stage retrieval pipelines
- Query understanding and expansion
- Retrieval evaluation and optimization
- Integration with vector stores
"""

from typing import List, Dict, Any, Optional, Tuple
from dataclasses import dataclass
from enum import Enum
import structlog

from langchain.schema import Document
from langchain_core.retrievers import BaseRetriever

import sys
import os
# Add the parent directory to the path so we can import from other modules
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from models.embeddings import EmbeddingGenerator
from config import config
from rag.vector_store import VectorStoreManager
from rag.document_loader import ComplaintDocumentLoader

logger = structlog.get_logger(__name__)


class RetrievalStrategy(Enum):
    """Retrieval strategy options"""
    VECTOR_ONLY = "vector_only"
    HYBRID = "hybrid"
    KEYWORD_ONLY = "keyword_only"
    SEMANTIC_EXPANSION = "semantic_expansion"


@dataclass
class RetrievalConfig:
    """Configuration for retrieval operations"""
    strategy: RetrievalStrategy = RetrievalStrategy.VECTOR_ONLY
    k: int = 5
    score_threshold: float = 0.0
    keyword_weight: float = 0.3
    vector_weight: float = 0.7
    query_expansion: bool = False
    max_query_terms: int = 10
    rerank: bool = True
    diversity_threshold: float = 0.1


class ComplaintRetriever:
    """
    Advanced retriever for NYC 311 complaint documents
    
    Features:
    - Multiple retrieval strategies
    - Query understanding and expansion
    - Hybrid vector-keyword search
    - Result reranking and diversity
    - Performance optimization
    
    Educational Value:
    - Advanced information retrieval concepts
    - Multi-modal search strategies
    - Query processing techniques
    - Result ranking and optimization
    """
    
    def __init__(self, 
                 vector_store_manager: Optional[VectorStoreManager] = None,
                 config: Optional[RetrievalConfig] = None):
        """
        Initialize complaint retriever
        
        Args:
            vector_store_manager: Vector store for semantic search
            config: Retrieval configuration
        """
        # Initialize components
        self.vector_store_manager = vector_store_manager or VectorStoreManager()
        self.embedding_generator = EmbeddingGenerator()
        self.document_loader = ComplaintDocumentLoader()
        self.config = config or RetrievalConfig()
        
        # Query processing components
        self.stopwords = self._load_stopwords()
        self.query_processors = {
            'expand': self._expand_query,
            'filter': self._extract_query_filters,
            'normalize': self._normalize_query
        }
        
        logger.info("ComplaintRetriever initialized",
                   strategy=self.config.strategy.value,
                   k=self.config.k)
    
    def _load_stopwords(self) -> set:
        """Load stopwords for query processing"""
        # Basic English stopwords for educational purposes
        return {
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have',
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'can', 'this', 'that', 'these', 'those', 'i', 'you',
            'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'
        }
    
    def retrieve(self, 
                query: str,
                filters: Optional[Dict[str, Any]] = None,
                strategy: Optional[RetrievalStrategy] = None) -> List[Document]:
        """
        Main retrieval entry point
        
        Educational Focus:
        - Strategy pattern implementation
        - Query preprocessing pipeline
        - Multi-stage retrieval
        
        Args:
            query: Search query
            filters: Optional metadata filters
            strategy: Override default retrieval strategy
            
        Returns:
            List of relevant documents
        """
        if not query or not query.strip():
            logger.warning("Empty query provided")
            return []
        
        # Use provided strategy or default
        retrieval_strategy = strategy or self.config.strategy
        
        logger.info("Starting document retrieval",
                   query_length=len(query),
                   strategy=retrieval_strategy.value,
                   has_filters=filters is not None)
        
        try:
            # Step 1: Query preprocessing
            processed_query = self._preprocess_query(query)
            
            # Step 2: Extract implicit filters from query
            implicit_filters = self._extract_query_filters(processed_query)
            combined_filters = {**(filters or {}), **implicit_filters}
            
            # Step 3: Execute retrieval strategy
            if retrieval_strategy == RetrievalStrategy.VECTOR_ONLY:
                documents = self._vector_retrieval(processed_query, combined_filters)
            elif retrieval_strategy == RetrievalStrategy.HYBRID:
                documents = self._hybrid_retrieval(processed_query, combined_filters)
            elif retrieval_strategy == RetrievalStrategy.KEYWORD_ONLY:
                documents = self._keyword_retrieval(processed_query, combined_filters)
            elif retrieval_strategy == RetrievalStrategy.SEMANTIC_EXPANSION:
                documents = self._semantic_expansion_retrieval(processed_query, combined_filters)
            else:
                raise ValueError(f"Unknown retrieval strategy: {retrieval_strategy}")
            
            # Step 4: Post-processing
            if self.config.rerank:
                documents = self._rerank_documents(query, documents)
            
            documents = self._ensure_diversity(documents)
            documents = documents[:self.config.k]
            
            logger.info("Document retrieval completed",
                       query=query[:50],
                       strategy=retrieval_strategy.value,
                       documents_found=len(documents))
            
            return documents
            
        except Exception as e:
            logger.error("Document retrieval failed",
                        query=query[:100],
                        strategy=retrieval_strategy.value,
                        error=str(e))
            return []
    
    def _preprocess_query(self, query: str) -> Dict[str, Any]:
        """
        Preprocess query for optimal retrieval
        
        Educational Focus:
        - Query understanding techniques
        - Text normalization strategies
        - Feature extraction from queries
        """
        normalized = self._normalize_query(query)
        expanded = self._expand_query(normalized) if self.config.query_expansion else normalized
        
        return {
            'original': query,
            'normalized': normalized,
            'expanded': expanded,
            'terms': self._extract_key_terms(normalized),
            'intent': self._detect_query_intent(query)
        }
    
    def _normalize_query(self, query: str) -> str:
        """Normalize query text"""
        # Basic normalization
        normalized = query.lower().strip()
        
        # Remove extra whitespace
        normalized = ' '.join(normalized.split())
        
        return normalized
    
    def _expand_query(self, query: str) -> str:
        """
        Expand query with related terms
        
        Educational Note:
        In production, this might use word embeddings, synonyms,
        or domain-specific expansion rules
        """
        # Simple expansion rules for NYC 311 domain
        expansions = {
            'noise': ['noise', 'loud', 'sound', 'music', 'construction'],
            'water': ['water', 'leak', 'plumbing', 'pipe', 'flooding'],
            'heat': ['heat', 'heating', 'hot water', 'boiler', 'radiator'],
            'parking': ['parking', 'car', 'vehicle', 'meter', 'permit'],
            'trash': ['trash', 'garbage', 'waste', 'sanitation', 'pickup'],
            'street': ['street', 'road', 'sidewalk', 'pothole', 'pavement']
        }
        
        expanded_terms = []
        query_lower = query.lower()
        
        for key, synonyms in expansions.items():
            if key in query_lower:
                expanded_terms.extend(synonyms)
        
        if expanded_terms:
            # Limit expansion to avoid overwhelming the query
            unique_terms = list(set(expanded_terms))[:self.config.max_query_terms]
            return query + ' ' + ' '.join(unique_terms)
        
        return query
    
    def _extract_key_terms(self, query: str) -> List[str]:
        """Extract key terms from query"""
        terms = query.lower().split()
        # Remove stopwords and short terms
        key_terms = [
            term for term in terms 
            if term not in self.stopwords and len(term) > 2
        ]
        return key_terms
    
    def _detect_query_intent(self, query: str) -> str:
        """
        Detect query intent for retrieval optimization
        
        Educational Focus:
        - Intent recognition patterns
        - Query classification
        - Domain-specific intent detection
        """
        query_lower = query.lower()
        
        # Question intents
        if any(word in query_lower for word in ['what', 'how many', 'show me', 'find']):
            return 'question'
        
        # Location-based intents
        if any(borough in query_lower for borough in ['manhattan', 'brooklyn', 'queens', 'bronx', 'staten']):
            return 'location_specific'
        
        # Time-based intents
        if any(time in query_lower for time in ['recent', 'last week', 'today', 'yesterday']):
            return 'temporal'
        
        # Status intents
        if any(status in query_lower for status in ['open', 'closed', 'resolved', 'escalated']):
            return 'status_query'
        
        return 'general'
    
    def _extract_query_filters(self, processed_query: Dict[str, Any]) -> Dict[str, Any]:
        """Extract implicit filters from query"""
        filters = {}
        query_text = processed_query.get('normalized', '')
        
        # Borough extraction
        boroughs = ['manhattan', 'brooklyn', 'queens', 'bronx', 'staten island']
        for borough in boroughs:
            if borough in query_text:
                filters['borough'] = borough.upper()
                break
        
        # Status extraction
        if 'open' in query_text:
            filters['status'] = 'Open'
        elif 'closed' in query_text:
            filters['status'] = 'Closed'
        
        return filters
    
    def _vector_retrieval(self, 
                         processed_query: Dict[str, Any],
                         filters: Dict[str, Any]) -> List[Document]:
        """
        Pure vector similarity retrieval
        
        Educational Focus:
        - Vector search fundamentals
        - Semantic similarity concepts
        - Performance optimization
        """
        query_text = processed_query.get('expanded', processed_query.get('normalized', ''))
        
        if not self.vector_store_manager.vector_store:
            logger.warning("No vector store available for vector retrieval")
            return []
        
        # Perform vector search
        results = self.vector_store_manager.search_similar_documents(
            query=query_text,
            k=self.config.k * 2,  # Get more for filtering
            score_threshold=self.config.score_threshold,
            filters=filters
        )
        
        # Convert to documents with metadata
        documents = []
        for doc, score in results:
            # Add retrieval metadata
            doc.metadata['retrieval_score'] = score
            doc.metadata['retrieval_method'] = 'vector'
            documents.append(doc)
        
        logger.debug("Vector retrieval completed",
                    results_found=len(documents))
        
        return documents
    
    def _hybrid_retrieval(self,
                         processed_query: Dict[str, Any],
                         filters: Dict[str, Any]) -> List[Document]:
        """
        Hybrid vector + keyword retrieval
        
        Educational Focus:
        - Combining different search modalities
        - Score fusion techniques
        - Balancing semantic and lexical matching
        """
        # Get vector results
        vector_docs = self._vector_retrieval(processed_query, filters)
        
        # Get keyword results
        keyword_docs = self._keyword_retrieval(processed_query, filters)
        
        # Combine and reweight scores
        combined_docs = self._combine_retrieval_results(
            vector_docs, keyword_docs,
            self.config.vector_weight, self.config.keyword_weight
        )
        
        logger.debug("Hybrid retrieval completed",
                    vector_results=len(vector_docs),
                    keyword_results=len(keyword_docs),
                    combined_results=len(combined_docs))
        
        return combined_docs
    
    def _keyword_retrieval(self,
                          processed_query: Dict[str, Any],
                          filters: Dict[str, Any]) -> List[Document]:
        """
        Keyword-based retrieval using TF-IDF or BM25 concepts
        
        Educational Note:
        This is a simplified implementation. Production systems
        would use dedicated search engines like Elasticsearch
        """
        query_terms = processed_query.get('terms', [])
        
        if not query_terms:
            return []
        
        # Simple keyword matching against documents
        # In production, this would use a proper search index
        if not hasattr(self.vector_store_manager, 'documents') or not self.vector_store_manager.documents:
            logger.warning("No documents available for keyword retrieval")
            return []
        
        scored_docs = []
        for doc in self.vector_store_manager.documents:
            score = self._calculate_keyword_score(doc.page_content.lower(), query_terms)
            if score > 0:
                # Add retrieval metadata
                doc.metadata['retrieval_score'] = score
                doc.metadata['retrieval_method'] = 'keyword'
                scored_docs.append((doc, score))
        
        # Sort by score and return top results
        scored_docs.sort(key=lambda x: x[1], reverse=True)
        documents = [doc for doc, _ in scored_docs[:self.config.k * 2]]
        
        logger.debug("Keyword retrieval completed",
                    query_terms=query_terms,
                    results_found=len(documents))
        
        return documents
    
    def _calculate_keyword_score(self, text: str, query_terms: List[str]) -> float:
        """Calculate simple keyword matching score"""
        text_terms = text.split()
        score = 0.0
        
        for term in query_terms:
            # Count term frequency
            tf = text_terms.count(term)
            if tf > 0:
                # Simple TF scoring (could be enhanced with IDF)
                score += tf / len(text_terms)
        
        return score
    
    def _semantic_expansion_retrieval(self,
                                    processed_query: Dict[str, Any],
                                    filters: Dict[str, Any]) -> List[Document]:
        """
        Retrieval with semantic query expansion
        
        Educational Focus:
        - Query expansion techniques
        - Semantic understanding
        - Progressive retrieval strategies
        """
        # Start with original query
        original_results = self._vector_retrieval(processed_query, filters)
        
        # If we have enough results, return them
        if len(original_results) >= self.config.k:
            return original_results
        
        # Otherwise, expand query and search again
        expanded_query = dict(processed_query)
        expanded_query['normalized'] = processed_query.get('expanded', processed_query.get('normalized', ''))
        
        expanded_results = self._vector_retrieval(expanded_query, filters)
        
        # Combine results, prioritizing original
        combined = original_results + [
            doc for doc in expanded_results 
            if not any(orig.page_content == doc.page_content for orig in original_results)
        ]
        
        logger.debug("Semantic expansion retrieval completed",
                    original_results=len(original_results),
                    expanded_results=len(expanded_results),
                    combined_results=len(combined))
        
        return combined
    
    def _combine_retrieval_results(self,
                                 vector_docs: List[Document],
                                 keyword_docs: List[Document],
                                 vector_weight: float,
                                 keyword_weight: float) -> List[Document]:
        """
        Combine results from different retrieval methods
        
        Educational Focus:
        - Score fusion techniques
        - Result deduplication
        - Multi-modal ranking
        """
        # Create combined score mapping
        doc_scores = {}
        
        # Add vector scores
        for doc in vector_docs:
            content_hash = hash(doc.page_content)
            doc_scores[content_hash] = {
                'document': doc,
                'vector_score': doc.metadata.get('retrieval_score', 0.0),
                'keyword_score': 0.0
            }
        
        # Add keyword scores
        for doc in keyword_docs:
            content_hash = hash(doc.page_content)
            if content_hash in doc_scores:
                doc_scores[content_hash]['keyword_score'] = doc.metadata.get('retrieval_score', 0.0)
            else:
                doc_scores[content_hash] = {
                    'document': doc,
                    'vector_score': 0.0,
                    'keyword_score': doc.metadata.get('retrieval_score', 0.0)
                }
        
        # Calculate combined scores
        combined_docs = []
        for data in doc_scores.values():
            combined_score = (
                data['vector_score'] * vector_weight +
                data['keyword_score'] * keyword_weight
            )
            
            doc = data['document']
            doc.metadata['retrieval_score'] = combined_score
            doc.metadata['retrieval_method'] = 'hybrid'
            doc.metadata['vector_score'] = data['vector_score']
            doc.metadata['keyword_score'] = data['keyword_score']
            
            combined_docs.append((doc, combined_score))
        
        # Sort by combined score
        combined_docs.sort(key=lambda x: x[1], reverse=True)
        
        return [doc for doc, _ in combined_docs]
    
    def _rerank_documents(self, original_query: str, documents: List[Document]) -> List[Document]:
        """
        Rerank documents for improved relevance
        
        Educational Focus:
        - Reranking strategies
        - Feature-based ranking
        - Query-document matching
        """
        if not documents:
            return documents
        
        # Simple reranking based on multiple factors
        scored_docs = []
        
        for doc in documents:
            rerank_score = self._calculate_rerank_score(original_query, doc)
            doc.metadata['rerank_score'] = rerank_score
            scored_docs.append((doc, rerank_score))
        
        # Sort by rerank score
        scored_docs.sort(key=lambda x: x[1], reverse=True)
        
        logger.debug("Document reranking completed",
                    original_count=len(documents),
                    reranked_count=len(scored_docs))
        
        return [doc for doc, _ in scored_docs]
    
    def _calculate_rerank_score(self, query: str, document: Document) -> float:
        """Calculate reranking score based on multiple factors"""
        base_score = document.metadata.get('retrieval_score', 0.0)
        
        # Factor 1: Title/type matching
        query_lower = query.lower()
        complaint_type = document.metadata.get('complaint_type', '').lower()
        type_bonus = 0.1 if any(word in complaint_type for word in query_lower.split()) else 0.0
        
        # Factor 2: Recent complaints (if timestamp available)
        recency_bonus = 0.0
        # This could check document timestamps for recency
        
        # Factor 3: High-risk complaints
        risk_bonus = 0.0
        risk_score = document.metadata.get('risk_score')
        if risk_score and float(risk_score) > 0.7:
            risk_bonus = 0.05
        
        return base_score + type_bonus + recency_bonus + risk_bonus
    
    def _ensure_diversity(self, documents: List[Document]) -> List[Document]:
        """
        Ensure diversity in results to avoid redundancy
        
        Educational Focus:
        - Result diversification
        - Redundancy detection
        - Quality vs. diversity trade-offs
        """
        if not documents or len(documents) <= 1:
            return documents
        
        diverse_docs = [documents[0]]  # Always include top result
        
        for doc in documents[1:]:
            # Check similarity with already selected documents
            is_diverse = True
            for selected_doc in diverse_docs:
                if self._are_documents_similar(doc, selected_doc):
                    is_diverse = False
                    break
            
            if is_diverse:
                diverse_docs.append(doc)
            
            # Stop if we have enough diverse results
            if len(diverse_docs) >= self.config.k:
                break
        
        logger.debug("Diversity filtering completed",
                    original_count=len(documents),
                    diverse_count=len(diverse_docs))
        
        return diverse_docs
    
    def _are_documents_similar(self, doc1: Document, doc2: Document) -> bool:
        """Check if two documents are too similar"""
        # Simple similarity check based on complaint type and location
        type1 = doc1.metadata.get('complaint_type', '')
        type2 = doc2.metadata.get('complaint_type', '')
        
        borough1 = doc1.metadata.get('borough', '')
        borough2 = doc2.metadata.get('borough', '')
        
        # Consider similar if same type and same borough
        if type1 == type2 and borough1 == borough2:
            # Check content similarity (simple word overlap)
            words1 = set(doc1.page_content.lower().split())
            words2 = set(doc2.page_content.lower().split())
            
            if len(words1) > 0 and len(words2) > 0:
                overlap = len(words1.intersection(words2))
                union = len(words1.union(words2))
                jaccard_similarity = overlap / union if union > 0 else 0
                
                return jaccard_similarity > self.config.diversity_threshold
        
        return False
    
    def get_retrieval_stats(self) -> Dict[str, Any]:
        """Get retrieval performance statistics"""
        return {
            'strategy': self.config.strategy.value,
            'k': self.config.k,
            'score_threshold': self.config.score_threshold,
            'query_expansion': self.config.query_expansion,
            'rerank': self.config.rerank,
            'vector_store_available': self.vector_store_manager.vector_store is not None,
            'document_count': len(self.vector_store_manager.documents) if self.vector_store_manager.documents else 0
        }


# Global retriever instance
complaint_retriever = ComplaintRetriever()