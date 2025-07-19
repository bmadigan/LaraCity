"""
OpenAI Client Setup with Comprehensive Error Handling

Educational Focus: 
- API client configuration best practices
- Robust error handling patterns
- Retry logic for production systems
- Rate limiting and timeout management
"""

import time
from typing import Optional, Dict, Any, List
from langchain_openai import ChatOpenAI, OpenAIEmbeddings
from openai import OpenAI, RateLimitError, APITimeoutError, APIError
import structlog
import sys
import os
# Add the parent directory to the path so we can import config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config import config

# Setup structured logging
logger = structlog.get_logger(__name__)


class OpenAIClient:
    """
    Robust OpenAI client with comprehensive error handling
    
    Features:
    - Automatic retry logic with exponential backoff
    - Rate limit handling
    - Timeout management  
    - Comprehensive error logging
    - Connection health monitoring
    """
    
    def __init__(self):
        """Initialize OpenAI client with configuration validation"""
        # Validate configuration first
        errors = config.validate_config()
        if errors:
            raise ValueError(f"Configuration errors: {', '.join(errors)}")
        
        logger.info("Initializing OpenAI client", 
                   model=config.OPENAI_MODEL,
                   embedding_model=config.OPENAI_EMBEDDING_MODEL)
        
        # Initialize LangChain ChatOpenAI client
        self.chat_client = ChatOpenAI(
            api_key=config.OPENAI_API_KEY,
            model=config.OPENAI_MODEL,
            temperature=config.TEMPERATURE,
            max_tokens=config.MAX_TOKENS,
            timeout=config.REQUEST_TIMEOUT,
            max_retries=3,  # Built-in retry logic
        )
        
        # Initialize direct OpenAI client for advanced operations
        self.openai_client = OpenAI(
            api_key=config.OPENAI_API_KEY,
            timeout=config.REQUEST_TIMEOUT,
        )
        
        # Initialize embeddings client
        self.embeddings_client = OpenAIEmbeddings(
            api_key=config.OPENAI_API_KEY,
            model=config.OPENAI_EMBEDDING_MODEL,
            timeout=config.REQUEST_TIMEOUT,
        )
        
        logger.info("OpenAI client initialized successfully")
    
    def generate_completion(self, 
                          prompt: str, 
                          max_retries: int = 3,
                          retry_delay: float = 1.0) -> str:
        """
        Generate completion with robust error handling
        
        Args:
            prompt: Input prompt for completion
            max_retries: Maximum number of retry attempts
            retry_delay: Initial delay between retries (exponential backoff)
            
        Returns:
            Generated completion text
            
        Raises:
            APIError: For unrecoverable API errors
            ValueError: For invalid inputs
        """
        if not prompt or not prompt.strip():
            raise ValueError("Prompt cannot be empty")
        
        logger.info("Generating completion", 
                   prompt_length=len(prompt),
                   model=config.OPENAI_MODEL)
        
        for attempt in range(max_retries + 1):
            try:
                # Use LangChain client for consistency
                response = self.chat_client.invoke(prompt)
                
                logger.info("Completion generated successfully",
                           attempt=attempt + 1,
                           response_length=len(response.content))
                
                return response.content
                
            except RateLimitError as e:
                if attempt == max_retries:
                    logger.error("Rate limit exceeded, max retries reached",
                               attempt=attempt + 1,
                               error=str(e))
                    raise
                
                # Wait longer for rate limits
                wait_time = retry_delay * (2 ** attempt) * 2
                logger.warning("Rate limit hit, retrying",
                             attempt=attempt + 1,
                             wait_time=wait_time)
                time.sleep(wait_time)
                
            except APITimeoutError as e:
                if attempt == max_retries:
                    logger.error("Timeout exceeded, max retries reached",
                               attempt=attempt + 1,
                               timeout=config.REQUEST_TIMEOUT,
                               error=str(e))
                    raise
                
                wait_time = retry_delay * (2 ** attempt)
                logger.warning("Request timeout, retrying",
                             attempt=attempt + 1,
                             wait_time=wait_time)
                time.sleep(wait_time)
                
            except APIError as e:
                logger.error("OpenAI API error",
                           attempt=attempt + 1,
                           error_type=type(e).__name__,
                           error=str(e))
                
                # Don't retry on authentication or other permanent errors
                if "authentication" in str(e).lower() or "invalid" in str(e).lower():
                    raise
                
                if attempt == max_retries:
                    raise
                
                wait_time = retry_delay * (2 ** attempt)
                time.sleep(wait_time)
                
            except Exception as e:
                logger.error("Unexpected error during completion",
                           attempt=attempt + 1,
                           error_type=type(e).__name__,
                           error=str(e))
                raise
        
        # Should never reach here due to raise in loops
        raise APIError("Maximum retries exceeded")
    
    def generate_embeddings(self, 
                          texts: List[str],
                          max_retries: int = 3) -> List[List[float]]:
        """
        Generate embeddings with error handling
        
        Args:
            texts: List of texts to embed
            max_retries: Maximum retry attempts
            
        Returns:
            List of embedding vectors
        """
        if not texts:
            raise ValueError("Texts list cannot be empty")
        
        logger.info("Generating embeddings",
                   text_count=len(texts),
                   model=config.OPENAI_EMBEDDING_MODEL)
        
        for attempt in range(max_retries + 1):
            try:
                embeddings = self.embeddings_client.embed_documents(texts)
                
                logger.info("Embeddings generated successfully",
                           embedding_count=len(embeddings),
                           dimension=len(embeddings[0]) if embeddings else 0)
                
                return embeddings
                
            except RateLimitError as e:
                if attempt == max_retries:
                    logger.error("Rate limit exceeded for embeddings",
                               attempt=attempt + 1)
                    raise
                
                wait_time = 2 ** attempt
                logger.warning("Rate limit hit, retrying embeddings",
                             attempt=attempt + 1,
                             wait_time=wait_time)
                time.sleep(wait_time)
                
            except Exception as e:
                logger.error("Error generating embeddings",
                           attempt=attempt + 1,
                           error=str(e))
                
                if attempt == max_retries:
                    raise
                
                time.sleep(1)
    
    def test_connection(self) -> Dict[str, Any]:
        """
        Test OpenAI API connection and return health status
        
        Returns:
            Dictionary with connection status and metadata
        """
        logger.info("Testing OpenAI connection")
        
        try:
            # Test chat completion
            test_response = self.generate_completion("Hello, this is a test.")
            
            # Test embeddings
            test_embeddings = self.generate_embeddings(["test text"])
            
            status = {
                "status": "healthy",
                "chat_completion": True,
                "embeddings": True,
                "model": config.OPENAI_MODEL,
                "embedding_model": config.OPENAI_EMBEDDING_MODEL,
                "embedding_dimension": len(test_embeddings[0]) if test_embeddings else 0,
                "test_response_length": len(test_response),
                "timestamp": time.time()
            }
            
            logger.info("OpenAI connection test successful", **status)
            return status
            
        except Exception as e:
            status = {
                "status": "error",
                "chat_completion": False,
                "embeddings": False,
                "error": str(e),
                "error_type": type(e).__name__,
                "timestamp": time.time()
            }
            
            logger.error("OpenAI connection test failed", **status)
            return status
    
    def get_model_info(self) -> Dict[str, Any]:
        """Get information about configured models"""
        return {
            "chat_model": config.OPENAI_MODEL,
            "embedding_model": config.OPENAI_EMBEDDING_MODEL,
            "temperature": config.TEMPERATURE,
            "max_tokens": config.MAX_TOKENS,
            "timeout": config.REQUEST_TIMEOUT,
            "embedding_dimension": config.EMBEDDING_DIMENSION
        }


# Global client instance for easy access
openai_client = OpenAI()