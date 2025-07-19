"""
RAG (Retrieval Augmented Generation) Chain using LCEL

Educational Focus:
- RAG system architecture and concepts
- Vector similarity search integration
- Context-aware question answering
- Document retrieval and ranking
- LangChain RAG patterns
"""

from typing import Dict, Any, List, Optional
from langchain.schema.output_parser import StrOutputParser
from langchain.schema.runnable import RunnablePassthrough, RunnableLambda
from langchain_core.messages import SystemMessage, HumanMessage
import structlog

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from models.openai_client import OpenAIClient
from models.embeddings import EmbeddingGenerator
from prompts.templates import QuestionAnsweringTemplate
from prompts.system_prompts import SystemPrompts
from config import config

logger = structlog.get_logger(__name__)


class RAGChain:
    """
    RAG chain for answering questions about NYC 311 complaints
    
    RAG Process:
    1. Question → Embedding → Vector Search → Retrieved Documents
    2. Question + Context → Prompt → LLM → Answer
    
    Educational Value:
    - Demonstrates complete RAG implementation
    - Shows vector search integration
    - Context ranking and selection
    - Question-answering with retrieved context
    """
    
    def __init__(self, vector_store=None):
        """
        Initialize RAG chain
        
        Args:
            vector_store: Optional vector store for document retrieval
                         If None, will use similarity search with provided embeddings
        """
        # Initialize components
        self.openai_client = OpenAIClient()
        self.embedding_generator = EmbeddingGenerator()
        self.qa_template = QuestionAnsweringTemplate()
        self.vector_store = vector_store
        
        # Build the LCEL RAG chain
        self.chain = self._build_rag_chain()
        
        logger.info("RAGChain initialized",
                   has_vector_store=vector_store is not None)
    
    def _build_rag_chain(self):
        """
        Build LCEL chain for RAG question answering
        
        Chain Structure:
        Question → Embedding → Retrieval → Context Ranking → Prompt → LLM → Answer
        """
        logger.info("Building LCEL RAG chain")
        
        # Step 1: Question preprocessing and embedding
        question_processing = (
            RunnablePassthrough.assign(
                # Generate embedding for question
                question_embedding=RunnableLambda(self._embed_question),
                
                # Extract any filters from question (simple NLP)
                extracted_filters=RunnableLambda(self._extract_filters_from_question)
            )
        )
        
        # Step 2: Document retrieval using vector search
        document_retrieval = (
            RunnablePassthrough.assign(
                # Retrieve relevant documents
                retrieved_documents=RunnableLambda(self._retrieve_documents),
                
                # Rank and filter documents
                context_documents=RunnableLambda(self._rank_and_filter_documents)
            )
        )
        
        # Step 3: Prompt assembly with context
        prompt_assembly = (
            RunnablePassthrough.assign(
                # Get system prompt for Q&A role
                system_prompt=RunnableLambda(lambda x: SystemPrompts.get_system_prompt('assistant')),
                
                # Format Q&A prompt with context
                qa_prompt=RunnableLambda(self._format_qa_prompt)
            )
        )
        
        # Step 4: Message formatting
        message_formatting = RunnableLambda(self._format_qa_messages)
        
        # Step 5: LLM invocation
        llm_call = self.openai_client.chat_client
        
        # Step 6: Output parsing and response formatting
        output_parser = StrOutputParser()
        response_formatter = RunnableLambda(self._format_response)
        
        # LCEL Chain Composition
        chain = (
            question_processing     # Question → Enhanced question data
            | document_retrieval    # Enhanced data → With retrieved docs
            | prompt_assembly      # With docs → With formatted prompts
            | message_formatting   # Prompts → Message list
            | llm_call            # Messages → LLM response
            | output_parser       # Response → String
            | response_formatter  # String → Formatted response dict
        )
        
        logger.info("LCEL RAG chain built successfully")
        return chain
    
    def _embed_question(self, input_data: Dict[str, Any]) -> List[float]:
        """Generate embedding for the user question"""
        question = input_data.get('question', '')
        if not question:
            raise ValueError("Question is required for RAG processing")
        
        embedding = self.embedding_generator.embed_user_question(question)
        
        logger.debug("Question embedding generated",
                    question_length=len(question),
                    embedding_dimension=len(embedding))
        
        return embedding
    
    def _extract_filters_from_question(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Extract filters from natural language question
        
        Educational Note:
        This is a simple implementation. Production systems might use
        more sophisticated NLP or dedicated intent recognition models.
        """
        question = input_data.get('question', '').lower()
        filters = {}
        
        # Borough extraction
        boroughs = ['manhattan', 'brooklyn', 'queens', 'bronx', 'staten island']
        for borough in boroughs:
            if borough in question:
                filters['borough'] = borough.upper()
                break
        
        # Complaint type extraction
        if 'noise' in question:
            filters['complaint_type'] = 'noise'
        elif 'water' in question:
            filters['complaint_type'] = 'water'
        elif 'heat' in question or 'heating' in question:
            filters['complaint_type'] = 'heat'
        elif 'parking' in question:
            filters['complaint_type'] = 'parking'
        elif 'traffic' in question:
            filters['complaint_type'] = 'traffic'
        
        # Status extraction
        if 'open' in question:
            filters['status'] = 'open'
        elif 'closed' in question:
            filters['status'] = 'closed'
        elif 'escalated' in question:
            filters['status'] = 'escalated'
        
        # Risk level extraction
        if 'high risk' in question or 'dangerous' in question or 'urgent' in question:
            filters['risk_level'] = 'high'
        elif 'low risk' in question or 'minor' in question:
            filters['risk_level'] = 'low'
        
        # Time period extraction (basic)
        if 'last week' in question or 'past week' in question:
            filters['time_period'] = 'last_week'
        elif 'last month' in question or 'past month' in question:
            filters['time_period'] = 'last_month'
        elif 'today' in question:
            filters['time_period'] = 'today'
        
        logger.debug("Extracted filters from question",
                    question=question[:100],
                    filters=filters)
        
        return filters
    
    def _retrieve_documents(self, input_data: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Retrieve relevant documents using vector search
        
        Educational Focus:
        - Vector similarity search concepts
        - Retrieval strategies and ranking
        - Fallback when no vector store available
        """
        question_embedding = input_data.get('question_embedding')
        extracted_filters = input_data.get('extracted_filters', {})
        
        if not question_embedding:
            logger.warning("No question embedding available for retrieval")
            return []
        
        # If we have a vector store, use it
        if self.vector_store:
            return self._retrieve_from_vector_store(
                question_embedding, 
                extracted_filters, 
                k=config.VECTOR_SEARCH_K
            )
        else:
            # Fallback: use provided complaint embeddings for similarity search
            complaint_embeddings = input_data.get('complaint_embeddings', [])
            complaint_data = input_data.get('complaint_data', [])
            
            if not complaint_embeddings or not complaint_data:
                logger.warning("No complaint embeddings or data provided for retrieval")
                return []
            
            return self._retrieve_with_similarity_search(
                question_embedding,
                complaint_embeddings,
                complaint_data,
                extracted_filters
            )
    
    def _retrieve_from_vector_store(self, 
                                   question_embedding: List[float],
                                   filters: Dict[str, Any],
                                   k: int = 5) -> List[Dict[str, Any]]:
        """Retrieve documents from vector store (when available)"""
        # This would integrate with actual vector store (FAISS, Pinecone, etc.)
        # For educational purposes, showing the interface
        logger.debug("Retrieving from vector store",
                    k=k,
                    filters=filters)
        
        # Placeholder implementation
        return []
    
    def _retrieve_with_similarity_search(self,
                                       question_embedding: List[float],
                                       complaint_embeddings: List[List[float]],
                                       complaint_data: List[Dict[str, Any]],
                                       filters: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Retrieve documents using similarity search with embeddings
        
        Educational Focus:
        - Vector similarity calculations
        - Filtering and ranking strategies
        - Combining vector search with metadata filters
        """
        if len(complaint_embeddings) != len(complaint_data):
            logger.error("Mismatch between embeddings and data lengths")
            return []
        
        # Generate complaint IDs for similarity search
        complaint_ids = [str(i) for i in range(len(complaint_data))]
        
        # Find similar complaints using embeddings
        similar_complaints = self.embedding_generator.find_similar_complaints(
            question_embedding,
            complaint_embeddings,
            complaint_ids,
            top_k=config.VECTOR_SEARCH_K * 2,  # Get more for filtering
            threshold=config.SIMILARITY_THRESHOLD * 0.7  # Lower threshold for more results
        )
        
        # Apply metadata filters
        filtered_results = []
        for result in similar_complaints:
            complaint_idx = int(result['complaint_id'])
            complaint = complaint_data[complaint_idx]
            
            # Apply extracted filters
            if self._matches_filters(complaint, filters):
                filtered_results.append({
                    **complaint,
                    'similarity_score': result['similarity'],
                    'retrieval_rank': len(filtered_results) + 1
                })
        
        # Limit to final k results
        final_results = filtered_results[:config.VECTOR_SEARCH_K]
        
        logger.debug("Documents retrieved",
                    total_candidates=len(similar_complaints),
                    after_filtering=len(filtered_results),
                    final_results=len(final_results))
        
        return final_results
    
    def _matches_filters(self, complaint: Dict[str, Any], filters: Dict[str, Any]) -> bool:
        """Check if complaint matches extracted filters"""
        if not filters:
            return True
        
        # Borough filter
        if 'borough' in filters:
            complaint_borough = complaint.get('borough', '').upper()
            if filters['borough'].upper() not in complaint_borough:
                return False
        
        # Complaint type filter
        if 'complaint_type' in filters:
            complaint_type = complaint.get('type', '').lower()
            filter_type = filters['complaint_type'].lower()
            if filter_type not in complaint_type:
                return False
        
        # Status filter
        if 'status' in filters:
            complaint_status = complaint.get('status', '').lower()
            if filters['status'].lower() != complaint_status:
                return False
        
        # Additional filter logic could be added here
        
        return True
    
    def _rank_and_filter_documents(self, input_data: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Rank and filter retrieved documents for optimal context
        
        Educational Focus:
        - Document ranking strategies
        - Context window management
        - Quality vs quantity trade-offs
        """
        documents = input_data.get('retrieved_documents', [])
        
        if not documents:
            return []
        
        # Sort by similarity score (already done in retrieval, but ensuring order)
        ranked_docs = sorted(documents, 
                           key=lambda x: x.get('similarity_score', 0), 
                           reverse=True)
        
        # Additional ranking factors could include:
        # - Recency (newer complaints might be more relevant)
        # - Complaint status (open vs closed)
        # - Risk level (high-risk complaints might be more important)
        
        logger.debug("Documents ranked and filtered",
                    input_count=len(documents),
                    final_count=len(ranked_docs))
        
        return ranked_docs
    
    def _format_qa_prompt(self, input_data: Dict[str, Any]) -> str:
        """Format Q&A prompt with retrieved context"""
        question = input_data.get('question', '')
        context_documents = input_data.get('context_documents', [])
        conversation_history = input_data.get('conversation_history', '')
        
        return self.qa_template.format_prompt(
            question=question,
            context_complaints=context_documents,
            conversation_history=conversation_history
        )
    
    def _format_qa_messages(self, input_data: Dict[str, Any]) -> List:
        """Format messages for Q&A chat model"""
        system_prompt = input_data['system_prompt']
        qa_prompt = input_data['qa_prompt']
        
        return [
            SystemMessage(content=system_prompt),
            HumanMessage(content=qa_prompt)
        ]
    
    def _format_response(self, llm_output: str) -> Dict[str, Any]:
        """Format the final response with metadata"""
        return {
            'answer': llm_output.strip(),
            'model_used': config.OPENAI_MODEL,
            'retrieval_method': 'rag_chain',
            'timestamp': None  # Could add actual timestamp
        }
    
    def answer_question(self, 
                       question: str,
                       complaint_embeddings: Optional[List[List[float]]] = None,
                       complaint_data: Optional[List[Dict[str, Any]]] = None,
                       conversation_history: str = "") -> Dict[str, Any]:
        """
        Main entry point for RAG question answering
        
        Args:
            question: User's question
            complaint_embeddings: Pre-computed complaint embeddings (if no vector store)
            complaint_data: Complaint data for context (if no vector store)
            conversation_history: Previous conversation context
            
        Returns:
            Answer with metadata
        """
        if not question or not question.strip():
            raise ValueError("Question cannot be empty")
        
        logger.info("Starting RAG question answering",
                   question_length=len(question),
                   has_embeddings=complaint_embeddings is not None,
                   has_data=complaint_data is not None)
        
        # Prepare input data
        input_data = {
            'question': question,
            'conversation_history': conversation_history,
            'complaint_embeddings': complaint_embeddings or [],
            'complaint_data': complaint_data or []
        }
        
        try:
            # Invoke the RAG chain
            result = self.chain.invoke(input_data)
            
            logger.info("RAG question answering completed",
                       answer_length=len(result.get('answer', '')))
            
            return result
            
        except Exception as e:
            logger.error("RAG question answering failed",
                        question=question[:100],
                        error=str(e))
            
            return {
                'answer': f"I'm sorry, I encountered an error while processing your question: {str(e)}",
                'error': str(e),
                'model_used': config.OPENAI_MODEL,
                'retrieval_method': 'rag_chain_error'
            }


# Global RAG chain instance
rag_chain = RAGChain()