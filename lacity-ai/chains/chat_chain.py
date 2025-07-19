"""
Chat Chain with Conversation Memory

Educational Focus:
- Conversational AI patterns
- Memory management and context preservation
- Multi-turn dialogue handling  
- State management in chat systems
"""

from typing import Dict, Any, List, Optional
from langchain.schema.output_parser import StrOutputParser
from langchain.schema.runnable import RunnablePassthrough, RunnableLambda
from langchain_core.messages import SystemMessage, HumanMessage, AIMessage
from langchain.memory import ConversationSummaryBufferMemory
import structlog

from ..models.openai_client import OpenAIClient
from ..prompts.system_prompts import SystemPrompts
from ..config import config
from .rag_chain import RAGChain

logger = structlog.get_logger(__name__)


class ChatChain:
    """
    Conversational chat chain with memory and RAG integration
    
    Features:
    - Conversation memory management
    - Context-aware responses
    - RAG integration for data queries
    - Multi-turn dialogue support
    - Session management
    
    Educational Value:
    - Shows conversational AI patterns
    - Demonstrates memory management
    - Integrates multiple chain types
    - Real-world chat system architecture
    """
    
    def __init__(self, max_token_limit: int = 2000):
        """
        Initialize chat chain with memory
        
        Args:
            max_token_limit: Maximum tokens to keep in memory buffer
        """
        # Initialize components
        self.openai_client = OpenAIClient()
        self.rag_chain = RAGChain()
        
        # Initialize conversation memory
        self.memory = ConversationSummaryBufferMemory(
            llm=self.openai_client.chat_client,
            max_token_limit=max_token_limit,
            return_messages=True
        )
        
        # Build the chat chain
        self.chain = self._build_chat_chain()
        
        # Session storage for multiple conversations
        self.sessions: Dict[str, ConversationSummaryBufferMemory] = {}
        
        logger.info("ChatChain initialized",
                   max_token_limit=max_token_limit)
    
    def _build_chat_chain(self):
        """
        Build LCEL chain for conversational chat
        
        Chain Structure:
        Input → Memory Retrieval → Intent Detection → RAG/Direct Response → Memory Update → Output
        """
        logger.info("Building LCEL chat chain")
        
        # Step 1: Load conversation memory and detect intent
        memory_and_intent = (
            RunnablePassthrough.assign(
                # Load conversation history
                conversation_history=RunnableLambda(self._load_conversation_history),
                
                # Detect if this is a data query that needs RAG
                needs_rag=RunnableLambda(self._detect_rag_intent),
                
                # Get appropriate system prompt
                system_prompt=RunnableLambda(self._get_system_prompt)
            )
        )
        
        # Step 2: Route to RAG or direct response
        response_routing = RunnableLambda(self._route_response)
        
        # Step 3: Format final response
        response_formatter = RunnableLambda(self._format_chat_response)
        
        # LCEL Chain Composition
        chain = (
            memory_and_intent     # Input → Enhanced with memory and intent
            | response_routing    # Enhanced → Response (via RAG or direct)
            | response_formatter  # Response → Formatted chat response
        )
        
        logger.info("LCEL chat chain built successfully")
        return chain
    
    def _load_conversation_history(self, input_data: Dict[str, Any]) -> str:
        """Load conversation history from memory"""
        session_id = input_data.get('session_id', 'default')
        
        # Get or create session memory
        if session_id not in self.sessions:
            self.sessions[session_id] = ConversationSummaryBufferMemory(
                llm=self.openai_client.chat_client,
                max_token_limit=2000,
                return_messages=True
            )
        
        memory = self.sessions[session_id]
        
        # Get conversation history
        history = memory.chat_memory.messages
        
        # Format history for prompt
        if not history:
            return "No previous conversation."
        
        formatted_history = []
        for message in history:
            if isinstance(message, HumanMessage):
                formatted_history.append(f"User: {message.content}")
            elif isinstance(message, AIMessage):
                formatted_history.append(f"Assistant: {message.content}")
        
        history_text = "\n".join(formatted_history[-6:])  # Last 6 messages
        
        logger.debug("Loaded conversation history",
                    session_id=session_id,
                    message_count=len(history),
                    formatted_length=len(history_text))
        
        return history_text
    
    def _detect_rag_intent(self, input_data: Dict[str, Any]) -> bool:
        """
        Detect if the user message requires RAG (data lookup)
        
        Educational Focus:
        - Intent detection patterns
        - Rule-based vs ML approaches
        - Context-aware routing
        """
        message = input_data.get('message', '').lower()
        
        # Keywords that suggest data queries
        data_keywords = [
            'show me', 'find', 'search', 'how many', 'what are', 'list',
            'complaints about', 'in brooklyn', 'in manhattan', 'in queens',
            'last week', 'last month', 'recent', 'open complaints',
            'high risk', 'escalated', 'resolved', 'agency', 'department'
        ]
        
        # Question words that often indicate data queries
        question_words = ['what', 'how', 'when', 'where', 'which', 'who']
        
        needs_rag = (
            any(keyword in message for keyword in data_keywords) or
            any(word in message.split()[:3] for word in question_words)  # Question word in first 3 words
        )
        
        logger.debug("RAG intent detection",
                    message=message[:100],
                    needs_rag=needs_rag)
        
        return needs_rag
    
    def _get_system_prompt(self, input_data: Dict[str, Any]) -> str:
        """Get appropriate system prompt based on intent"""
        needs_rag = input_data.get('needs_rag', False)
        
        if needs_rag:
            return SystemPrompts.get_system_prompt('assistant')  # Data assistant
        else:
            return SystemPrompts.get_system_prompt('chat')  # Chat agent
    
    def _route_response(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Route to RAG or direct response based on intent
        
        Educational Focus:
        - Conditional routing in chains
        - Integration between different chain types
        - Response strategy selection
        """
        needs_rag = input_data.get('needs_rag', False)
        message = input_data.get('message', '')
        conversation_history = input_data.get('conversation_history', '')
        
        if needs_rag:
            # Use RAG for data queries
            logger.debug("Routing to RAG for data query")
            
            complaint_embeddings = input_data.get('complaint_embeddings')
            complaint_data = input_data.get('complaint_data')
            
            rag_result = self.rag_chain.answer_question(
                question=message,
                complaint_embeddings=complaint_embeddings,
                complaint_data=complaint_data,
                conversation_history=conversation_history
            )
            
            return {
                'response': rag_result['answer'],
                'response_type': 'rag',
                'metadata': rag_result
            }
        else:
            # Use direct chat for general conversation
            logger.debug("Routing to direct chat response")
            
            system_prompt = input_data.get('system_prompt')
            
            messages = [
                SystemMessage(content=system_prompt),
                HumanMessage(content=f"Conversation history:\n{conversation_history}\n\nUser: {message}")
            ]
            
            response = self.openai_client.chat_client.invoke(messages)
            
            return {
                'response': response.content,
                'response_type': 'direct',
                'metadata': {'model_used': config.OPENAI_MODEL}
            }
    
    def _format_chat_response(self, response_data: Dict[str, Any]) -> Dict[str, Any]:
        """Format the final chat response with metadata"""
        return {
            'message': response_data['response'],
            'type': response_data['response_type'],
            'metadata': response_data.get('metadata', {}),
            'timestamp': None  # Could add actual timestamp
        }
    
    def chat(self, 
             message: str,
             session_id: str = 'default',
             complaint_embeddings: Optional[List[List[float]]] = None,
             complaint_data: Optional[List[Dict[str, Any]]] = None) -> Dict[str, Any]:
        """
        Main entry point for chat interaction
        
        Args:
            message: User message
            session_id: Session identifier for conversation memory
            complaint_embeddings: Pre-computed embeddings for RAG
            complaint_data: Complaint data for RAG
            
        Returns:
            Chat response with metadata
        """
        if not message or not message.strip():
            raise ValueError("Message cannot be empty")
        
        logger.info("Processing chat message",
                   session_id=session_id,
                   message_length=len(message))
        
        # Prepare input data
        input_data = {
            'message': message.strip(),
            'session_id': session_id,
            'complaint_embeddings': complaint_embeddings,
            'complaint_data': complaint_data
        }
        
        try:
            # Invoke the chat chain
            result = self.chain.invoke(input_data)
            
            # Update memory with the conversation
            self._update_memory(session_id, message, result['message'])
            
            logger.info("Chat message processed successfully",
                       session_id=session_id,
                       response_type=result.get('type'),
                       response_length=len(result.get('message', '')))
            
            return result
            
        except Exception as e:
            logger.error("Chat processing failed",
                        session_id=session_id,
                        message=message[:100],
                        error=str(e))
            
            error_response = {
                'message': "I'm sorry, I encountered an error processing your message. Please try again.",
                'type': 'error',
                'metadata': {'error': str(e)},
                'timestamp': None
            }
            
            # Still update memory with error for context
            self._update_memory(session_id, message, error_response['message'])
            
            return error_response
    
    def _update_memory(self, session_id: str, user_message: str, ai_response: str):
        """Update conversation memory with new exchange"""
        if session_id not in self.sessions:
            self.sessions[session_id] = ConversationSummaryBufferMemory(
                llm=self.openai_client.chat_client,
                max_token_limit=2000,
                return_messages=True
            )
        
        memory = self.sessions[session_id]
        
        # Add messages to memory
        memory.chat_memory.add_user_message(user_message)
        memory.chat_memory.add_ai_message(ai_response)
        
        logger.debug("Updated conversation memory",
                    session_id=session_id,
                    total_messages=len(memory.chat_memory.messages))
    
    def get_conversation_history(self, session_id: str) -> List[Dict[str, Any]]:
        """Get formatted conversation history for a session"""
        if session_id not in self.sessions:
            return []
        
        memory = self.sessions[session_id]
        history = []
        
        for message in memory.chat_memory.messages:
            if isinstance(message, HumanMessage):
                history.append({
                    'role': 'user',
                    'content': message.content,
                    'timestamp': None  # Could add timestamps
                })
            elif isinstance(message, AIMessage):
                history.append({
                    'role': 'assistant', 
                    'content': message.content,
                    'timestamp': None
                })
        
        return history
    
    def clear_conversation(self, session_id: str):
        """Clear conversation history for a session"""
        if session_id in self.sessions:
            self.sessions[session_id].clear()
            logger.info("Conversation cleared", session_id=session_id)
    
    def list_sessions(self) -> List[str]:
        """Get list of active session IDs"""
        return list(self.sessions.keys())
    
    def get_session_stats(self, session_id: str) -> Dict[str, Any]:
        """Get statistics for a conversation session"""
        if session_id not in self.sessions:
            return {'exists': False}
        
        memory = self.sessions[session_id]
        messages = memory.chat_memory.messages
        
        user_messages = [m for m in messages if isinstance(m, HumanMessage)]
        ai_messages = [m for m in messages if isinstance(m, AIMessage)]
        
        return {
            'exists': True,
            'total_messages': len(messages),
            'user_messages': len(user_messages),
            'ai_messages': len(ai_messages),
            'memory_buffer_size': len(memory.buffer) if hasattr(memory, 'buffer') else 0
        }


# Global chat chain instance
chat_chain = ChatChain()