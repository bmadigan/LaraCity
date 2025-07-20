#!/usr/bin/env python3
"""
LangChain Runner - PHP-Python Bridge Entry Point

Educational Focus:
- Inter-process communication patterns
- Command-line interface design
- Error handling and logging
- Integration architecture
- Production deployment considerations

This script serves as the main entry point for PHP to interact with
the LangChain system. It handles command routing, argument parsing,
and response formatting.
"""

import sys
import json
import argparse
import traceback
from pathlib import Path
from typing import Dict, Any, List, Optional

try:
    import structlog
    STRUCTLOG_AVAILABLE = True
except ImportError:
    STRUCTLOG_AVAILABLE = False
    import logging
    structlog = logging

# Add the project root to Python path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

from config import config
from chains.analysis_chain import ComplaintAnalysisChain
from chains.rag_chain import RAGChain
from chains.chat_chain import ChatChain
from rag.document_loader import complaint_document_loader
from rag.vector_store import vector_store_manager
from rag.pgvector_store import PGVectorStoreManager
from rag.retriever import complaint_retriever
from models.embeddings import embedding_generator

# Configure logging to stderr (so it doesn't interfere with JSON output on stdout)
import logging
logging.basicConfig(
    level=logging.WARNING,  # Set to WARNING to reduce log output during JSON operations
    format='%(asctime)s [%(levelname)s] %(message)s',
    stream=sys.stderr  # Important: send logs to stderr, not stdout
)

# Disable verbose logging for embedding operations to ensure clean JSON output
logging.getLogger().setLevel(logging.WARNING)

# Use standard logging instead of structlog to avoid configuration issues
logger = logging.getLogger(__name__)


class LangChainRunner:
    """
    Main runner class for LangChain operations
    
    Educational Focus:
    - Command pattern implementation
    - Modular operation design
    - Error isolation and handling
    - Performance monitoring
    """
    
    def __init__(self):
        """Initialize the runner with all components"""
        self.operations = {
            'analyze_complaint': self.analyze_complaint,
            'answer_question': self.answer_question,
            'chat': self.chat,
            'create_embeddings': self.create_embeddings,
            'create_vector_store': self.create_vector_store,
            'search_documents': self.search_documents,
            'sync_pgvector': self.sync_pgvector,
            'pgvector_search': self.pgvector_search,
            'pgvector_stats': self.pgvector_stats,
            'health_check': self.health_check,
            'get_stats': self.get_stats
        }
        
        # Initialize pgvector manager
        try:
            self.pgvector_manager = PGVectorStoreManager()
            logger.info("PGVector manager initialized successfully")
        except Exception as e:
            logger.warning("Failed to initialize PGVector manager", error=str(e))
            self.pgvector_manager = None
        
        logger.info("LangChainRunner initialized",
                   available_operations=list(self.operations.keys()))
    
    def run(self, operation: str, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Execute the specified operation with provided data
        
        Args:
            operation: Operation name to execute
            data: Input data for the operation
            
        Returns:
            Operation result dictionary
        """
        if operation not in self.operations:
            return self._error_response(f"Unknown operation: {operation}")
        
        logger.info("Executing operation",
                   operation=operation,
                   data_keys=list(data.keys()) if isinstance(data, dict) else None)
        
        try:
            result = self.operations[operation](data)
            
            logger.info("Operation completed successfully",
                       operation=operation,
                       result_type=type(result).__name__)
            
            return self._success_response(result)
            
        except Exception as e:
            logger.error("Operation failed",
                        operation=operation,
                        error=str(e),
                        traceback=traceback.format_exc())
            
            return self._error_response(f"Operation failed: {str(e)}")
    
    def analyze_complaint(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Analyze a single complaint using the analysis chain
        
        Educational Focus:
        - Single document analysis
        - Chain invocation patterns
        - Error handling in AI operations
        """
        complaint_data = data.get('complaint_data')
        if not complaint_data:
            raise ValueError("complaint_data is required")
        
        logger.debug("Starting complaint analysis",
                    complaint_id=complaint_data.get('id'))
        
        # Create and use analysis chain instance
        analysis_chain = ComplaintAnalysisChain()
        analysis_result = analysis_chain.analyze_complaint(complaint_data)
        
        return {
            'analysis': analysis_result,
            'complaint_id': complaint_data.get('id'),
            'model_used': config.OPENAI_MODEL
        }
    
    def answer_question(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Answer a question using RAG (Retrieval Augmented Generation)
        
        Educational Focus:
        - RAG system integration
        - Question-answering workflows
        - Context management
        """
        question = data.get('question')
        if not question:
            raise ValueError("question is required")
        
        complaint_embeddings = data.get('complaint_embeddings', [])
        complaint_data = data.get('complaint_data', [])
        conversation_history = data.get('conversation_history', '')
        
        logger.debug("Starting RAG question answering",
                    question_length=len(question),
                    has_embeddings=len(complaint_embeddings) > 0,
                    has_data=len(complaint_data) > 0)
        
        # Create and use RAG chain instance
        rag_chain = RAGChain()
        rag_result = rag_chain.answer_question(
            question=question,
            complaint_embeddings=complaint_embeddings,
            complaint_data=complaint_data,
            conversation_history=conversation_history
        )
        
        return {
            'answer': rag_result.get('answer', ''),
            'retrieval_method': rag_result.get('retrieval_method', 'unknown'),
            'model_used': rag_result.get('model_used', config.OPENAI_MODEL),
            'question': question
        }
    
    def chat(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Handle conversational chat with memory
        
        Educational Focus:
        - Conversational AI patterns
        - Session management
        - Memory integration
        """
        message = data.get('message')
        if not message:
            raise ValueError("message is required")
        
        session_id = data.get('session_id', 'default')
        complaint_embeddings = data.get('complaint_embeddings', [])
        complaint_data = data.get('complaint_data', [])
        
        logger.debug("Starting chat interaction",
                    message_length=len(message),
                    session_id=session_id)
        
        # Create and use chat chain instance
        chat_chain = ChatChain()
        chat_result = chat_chain.chat(
            message=message,
            session_id=session_id,
            complaint_embeddings=complaint_embeddings,
            complaint_data=complaint_data
        )
        
        return {
            'response': chat_result.get('message', ''),
            'response_type': chat_result.get('type', 'unknown'),
            'session_id': session_id,
            'metadata': chat_result.get('metadata', {})
        }
    
    def create_embeddings(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Create embeddings for text or complaints
        
        Educational Focus:
        - Embedding generation workflows
        - Batch processing patterns
        - Performance optimization
        """
        texts = data.get('texts', [])
        complaints = data.get('complaints', [])
        
        if not texts and not complaints:
            raise ValueError("Either texts or complaints must be provided")
        
        embeddings = []
        
        if texts:
            logger.debug("Creating embeddings for texts",
                        text_count=len(texts))
            embeddings = embedding_generator.embeddings.embed_documents(texts)
        
        elif complaints:
            logger.debug("Creating embeddings for complaints",
                        complaint_count=len(complaints))
            
            # Convert complaints to documents first
            documents = complaint_document_loader.load_complaints_as_documents(complaints)
            
            # Extract text content
            complaint_texts = [doc.page_content for doc in documents]
            embeddings = embedding_generator.embeddings.embed_documents(complaint_texts)
        
        return {
            'embeddings': embeddings,
            'count': len(embeddings),
            'dimension': len(embeddings[0]) if embeddings else 0,
            'model': config.OPENAI_EMBEDDING_MODEL
        }
    
    def create_vector_store(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Create and save a vector store from complaint data
        
        Educational Focus:
        - Vector store creation workflows
        - Data indexing strategies
        - Persistence patterns
        """
        complaints = data.get('complaints', [])
        store_path = data.get('store_path')
        chunk_documents = data.get('chunk_documents', True)
        
        if not complaints:
            raise ValueError("complaints data is required")
        
        logger.debug("Creating vector store",
                    complaint_count=len(complaints),
                    chunk_documents=chunk_documents)
        
        # Create vector store
        success = vector_store_manager.create_vector_store_from_complaints(
            complaints, 
            chunk_documents=chunk_documents
        )
        
        if not success:
            raise RuntimeError("Failed to create vector store")
        
        # Save if path provided
        if store_path:
            save_success = vector_store_manager.save_vector_store(store_path)
            if not save_success:
                logger.warning("Failed to save vector store to specified path")
        
        # Get store statistics
        stats = vector_store_manager.get_store_stats()
        
        return {
            'created': success,
            'saved': bool(store_path and save_success) if store_path else False,
            'store_path': store_path,
            'stats': stats
        }
    
    def search_documents(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Search documents using the retriever
        
        Educational Focus:
        - Document retrieval workflows
        - Search parameter tuning
        - Result formatting
        """
        query = data.get('query')
        if not query:
            raise ValueError("query is required")
        
        k = data.get('k', 5)
        filters = data.get('filters', {})
        strategy = data.get('strategy', 'vector_only')
        
        logger.debug("Searching documents",
                    query_length=len(query),
                    k=k,
                    strategy=strategy)
        
        # Set retrieval strategy
        from rag.retriever import RetrievalStrategy
        try:
            retrieval_strategy = RetrievalStrategy(strategy)
        except ValueError:
            retrieval_strategy = RetrievalStrategy.VECTOR_ONLY
        
        # Perform search
        documents = complaint_retriever.retrieve(
            query=query,
            filters=filters,
            strategy=retrieval_strategy
        )
        
        # Format results
        formatted_results = []
        for doc in documents[:k]:
            formatted_results.append({
                'content': doc.page_content,
                'metadata': doc.metadata,
                'score': doc.metadata.get('retrieval_score', 0.0)
            })
        
        return {
            'results': formatted_results,
            'query': query,
            'count': len(formatted_results),
            'strategy': strategy
        }
    
    def health_check(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Perform system health check
        
        Educational Focus:
        - System diagnostics
        - Component availability checking
        - Status reporting
        """
        logger.debug("Performing health check")
        
        health_status = {
            'status': 'healthy',
            'components': {},
            'config': {
                'openai_model': config.OPENAI_MODEL,
                'embedding_model': config.OPENAI_EMBEDDING_MODEL,
                'embedding_dimension': config.EMBEDDING_DIMENSION
            }
        }
        
        # Check OpenAI client
        try:
            from models.openai_client import OpenAIClient
            client = OpenAIClient()
            test_response = client.generate_completion("Test", max_retries=1)
            health_status['components']['openai'] = {
                'status': 'healthy',
                'response_length': len(test_response) if test_response else 0
            }
        except Exception as e:
            health_status['components']['openai'] = {
                'status': 'unhealthy',
                'error': str(e)
            }
            health_status['status'] = 'degraded'
        
        # Check embedding generator
        try:
            test_embedding = embedding_generator.embed_user_question("test")
            health_status['components']['embeddings'] = {
                'status': 'healthy',
                'dimension': len(test_embedding)
            }
        except Exception as e:
            health_status['components']['embeddings'] = {
                'status': 'unhealthy',
                'error': str(e)
            }
            health_status['status'] = 'degraded'
        
        # Check vector store
        try:
            vector_stats = vector_store_manager.get_store_stats()
            health_status['components']['vector_store'] = {
                'status': 'healthy' if vector_stats.get('exists') else 'not_initialized',
                'stats': vector_stats
            }
        except Exception as e:
            health_status['components']['vector_store'] = {
                'status': 'unhealthy',
                'error': str(e)
            }
        
        return health_status
    
    def get_stats(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Get system statistics and performance metrics
        
        Educational Focus:
        - Performance monitoring
        - Statistics collection
        - System introspection
        """
        logger.debug("Collecting system statistics")
        
        stats = {
            'system': {
                'python_version': sys.version,
                'available_operations': list(self.operations.keys())
            },
            'components': {}
        }
        
        # Document loader stats
        try:
            # Create sample to get stats
            sample_docs = complaint_document_loader.load_complaints_as_documents([])
            doc_stats = complaint_document_loader.get_document_stats(sample_docs)
            stats['components']['document_loader'] = doc_stats
        except Exception as e:
            stats['components']['document_loader'] = {'error': str(e)}
        
        # Vector store stats
        try:
            vector_stats = vector_store_manager.get_store_stats()
            stats['components']['vector_store'] = vector_stats
        except Exception as e:
            stats['components']['vector_store'] = {'error': str(e)}
        
        # Retriever stats
        try:
            retriever_stats = complaint_retriever.get_retrieval_stats()
            stats['components']['retriever'] = retriever_stats
        except Exception as e:
            stats['components']['retriever'] = {'error': str(e)}
        
        # Chat chain stats
        try:
            chat_stats = {
                'active_sessions': len(chat_chain.sessions),
                'session_ids': chat_chain.list_sessions()
            }
            stats['components']['chat'] = chat_stats
        except Exception as e:
            stats['components']['chat'] = {'error': str(e)}
        
        return stats
    
    def sync_pgvector(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Synchronize pgvector store with Laravel data
        
        Educational Focus:
        - Cross-platform data synchronization
        - ETL patterns for vector data
        - Progress tracking and error handling
        """
        logger.info("Starting pgvector synchronization")
        
        if not self.pgvector_manager:
            return self._error_response("PGVector manager not available")
        
        try:
            sync_stats = self.pgvector_manager.sync_with_laravel_data()
            
            logger.info("PGVector sync completed", **sync_stats)
            
            return self._success_response({
                'sync_completed': True,
                'statistics': sync_stats,
                'message': f"Processed {sync_stats.get('complaints_processed', 0)} complaints, "
                          f"created {sync_stats.get('embeddings_created', 0)} embeddings"
            })
        
        except Exception as e:
            logger.error("PGVector sync failed", error=str(e))
            return self._error_response(f"Sync failed: {str(e)}")
    
    def pgvector_search(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Search documents using pgvector similarity
        
        Educational Focus:
        - Vector similarity search
        - PostgreSQL vector operations
        - Result ranking and filtering
        """
        query = data.get('query', '')
        document_type = data.get('document_type')
        threshold = data.get('threshold', 0.7)
        limit = data.get('limit', 10)
        
        logger.info("Starting pgvector search", 
                   query_length=len(query),
                   document_type=document_type,
                   threshold=threshold)
        
        if not self.pgvector_manager:
            return self._error_response("PGVector manager not available")
        
        if not query:
            return self._error_response("Query text required")
        
        try:
            results = self.pgvector_manager.search_similar_documents(
                query=query,
                document_type=document_type,
                threshold=threshold,
                limit=limit
            )
            
            logger.info("PGVector search completed", results_count=len(results))
            
            return self._success_response({
                'results': results,
                'query': query,
                'search_params': {
                    'document_type': document_type,
                    'threshold': threshold,
                    'limit': limit
                },
                'total_results': len(results)
            })
        
        except Exception as e:
            logger.error("PGVector search failed", error=str(e))
            return self._error_response(f"Search failed: {str(e)}")
    
    def pgvector_stats(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Get pgvector store statistics
        
        Educational Focus:
        - Vector store monitoring
        - Database statistics
        - Performance metrics
        """
        logger.debug("Getting pgvector statistics")
        
        if not self.pgvector_manager:
            return self._error_response("PGVector manager not available")
        
        try:
            stats = self.pgvector_manager.get_statistics()
            
            logger.info("Retrieved pgvector statistics", 
                       total_embeddings=stats.get('total_embeddings', 0))
            
            return self._success_response(stats)
        
        except Exception as e:
            logger.error("Failed to get pgvector statistics", error=str(e))
            return self._error_response(f"Statistics retrieval failed: {str(e)}")
    
    def _success_response(self, data: Any) -> Dict[str, Any]:
        """Format successful response"""
        return {
            'success': True,
            'data': data,
            'error': None
        }
    
    def _error_response(self, error_message: str) -> Dict[str, Any]:
        """Format error response"""
        return {
            'success': False,
            'data': None,
            'error': error_message
        }


def main():
    """
    Main entry point for command-line usage
    
    Educational Focus:
    - CLI design patterns
    - Argument parsing
    - Input validation
    - Output formatting
    """
    parser = argparse.ArgumentParser(
        description="LangChain Runner - PHP-Python Bridge",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Analyze a complaint
  python langchain_runner.py analyze_complaint '{"complaint_data": {...}}'
  
  # Answer a question
  python langchain_runner.py answer_question '{"question": "How many complaints in Brooklyn?"}'
  
  # Health check
  python langchain_runner.py health_check '{}'
        """
    )
    
    parser.add_argument(
        'operation',
        help='Operation to perform',
        choices=[
            'analyze_complaint', 'answer_question', 'chat', 'create_embeddings',
            'create_vector_store', 'search_documents', 'health_check', 'get_stats'
        ]
    )
    
    parser.add_argument(
        'data',
        help='JSON data for the operation (use {} for operations that don\'t need data)'
    )
    
    parser.add_argument(
        '--verbose', '-v',
        action='store_true',
        help='Enable verbose logging'
    )
    
    args = parser.parse_args()
    
    # Configure logging level
    if args.verbose:
        import logging
        logging.basicConfig(level=logging.DEBUG)
    
    try:
        # Parse input data
        try:
            input_data = json.loads(args.data)
        except json.JSONDecodeError as e:
            logger.error("Invalid JSON data", error=str(e))
            print(json.dumps({
                'success': False,
                'data': None,
                'error': f'Invalid JSON data: {str(e)}'
            }))
            sys.exit(1)
        
        # Initialize and run
        runner = LangChainRunner()
        result = runner.run(args.operation, input_data)
        
        # Output result as JSON
        print(json.dumps(result, indent=2))
        
        # Exit with appropriate code
        sys.exit(0 if result['success'] else 1)
        
    except KeyboardInterrupt:
        logger.info("Operation interrupted by user")
        print(json.dumps({
            'success': False,
            'data': None,
            'error': 'Operation interrupted by user'
        }))
        sys.exit(130)  # Standard exit code for SIGINT
        
    except Exception as e:
        logger.error("Unexpected error", error=str(e), traceback=traceback.format_exc())
        print(json.dumps({
            'success': False,
            'data': None,
            'error': f'Unexpected error: {str(e)}'
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()