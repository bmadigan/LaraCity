"""
PGVector Store Manager for RAG System

Educational Focus:
- PostgreSQL vector store integration
- pgvector extension usage patterns  
- Direct database vector operations
- Production-ready vector storage
- Laravel-Python data synchronization
"""

import os
import json
import hashlib
from typing import List, Dict, Any, Optional, Tuple
import numpy as np
import structlog

try:
    import psycopg2
    from psycopg2.extras import RealDictCursor
    PSYCOPG2_AVAILABLE = True
except ImportError:
    PSYCOPG2_AVAILABLE = False
    psycopg2 = None

try:
    from langchain_community.vectorstores.pgvector import PGVector
    from langchain.schema import Document
    LANGCHAIN_PGVECTOR_AVAILABLE = True
except ImportError:
    LANGCHAIN_PGVECTOR_AVAILABLE = False
    PGVector = None

import sys
import os
# Add the parent directory to the path so we can import from other modules
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from models.embeddings import EmbeddingGenerator
from config import config
from rag.document_loader import ComplaintDocumentLoader

logger = structlog.get_logger(__name__)


class PGVectorStoreManager:
    """
    Production-ready pgvector store manager
    
    Features:
    - Direct PostgreSQL vector operations
    - Integration with Laravel document_embeddings table
    - Efficient bulk operations
    - Vector similarity search
    - Metadata filtering with SQL
    
    Educational Value:
    - Real vector database integration
    - PostgreSQL vector extension usage
    - Cross-platform data consistency
    - Production performance patterns
    """
    
    def __init__(self):
        self.embedding_generator = EmbeddingGenerator()
        self.connection_params = self._get_connection_params()
        self.vector_store = None
        
        # Test connection on initialization
        if not self._test_connection():
            raise ConnectionError("Failed to connect to PostgreSQL with pgvector")
            
        logger.info("PGVectorStoreManager initialized successfully")

    def _get_connection_params(self) -> Dict[str, str]:
        """Get PostgreSQL connection parameters from environment/config"""
        return {
            'host': os.getenv('DB_HOST', '127.0.0.1'),
            'port': int(os.getenv('DB_PORT', '5432')),
            'database': os.getenv('DB_DATABASE', 'laracity'),
            'user': os.getenv('DB_USERNAME', 'root'),
            'password': os.getenv('DB_PASSWORD', ''),
        }

    def _test_connection(self) -> bool:
        """Test database connection and pgvector availability"""
        if not PSYCOPG2_AVAILABLE:
            logger.error("psycopg2 not available for pgvector operations")
            return False
            
        try:
            conn = psycopg2.connect(**self.connection_params)
            with conn.cursor() as cur:
                # Test pgvector extension
                cur.execute("SELECT extname FROM pg_extension WHERE extname = 'vector';")
                result = cur.fetchone()
                
                if not result:
                    logger.error("pgvector extension not installed")
                    return False
                    
                # Test vector operations
                cur.execute("SELECT '[1,2,3]'::vector <-> '[1,2,4]'::vector AS distance;")
                distance = cur.fetchone()[0]
                logger.info("pgvector test successful", distance=distance)
                
            conn.close()
            return True
            
        except Exception as e:
            logger.error("pgvector connection test failed", error=str(e))
            return False

    def create_embedding_record(self, document: Document, document_type: str, 
                              document_id: int = None) -> bool:
        """
        Create embedding record in Laravel's document_embeddings table
        
        Educational Focus:
        - Cross-platform data consistency
        - Hash-based deduplication
        - Metadata preservation
        - Error handling patterns
        """
        try:
            # Generate embedding for document content
            embedding = self.embedding_generator.embed_user_question(document.page_content)
            
            # Create content hash for deduplication
            content_hash = hashlib.sha256(document.page_content.encode()).hexdigest()
            
            # Prepare metadata
            metadata = document.metadata.copy() if document.metadata else {}
            metadata.update({
                'source': 'python_langchain',
                'created_by': 'pgvector_store_manager',
                'content_length': len(document.page_content)
            })
            
            conn = psycopg2.connect(**self.connection_params)
            with conn.cursor() as cur:
                # Check if embedding already exists
                cur.execute("""
                    SELECT id FROM document_embeddings 
                    WHERE document_hash = %s
                """, (content_hash,))
                
                existing = cur.fetchone()
                if existing:
                    logger.info("Embedding already exists", 
                              content_hash=content_hash, 
                              existing_id=existing[0])
                    conn.close()
                    return True
                
                # Insert new embedding record
                cur.execute("""
                    INSERT INTO document_embeddings 
                    (document_type, document_id, document_hash, content, metadata, 
                     embedding_model, embedding_dimension, embedding, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    RETURNING id
                """, (
                    document_type,
                    document_id,
                    content_hash,
                    document.page_content,
                    json.dumps(metadata),
                    config.OPENAI_EMBEDDING_MODEL,
                    len(embedding),
                    f"[{','.join(map(str, embedding))}]"
                ))
                
                new_id = cur.fetchone()[0]
                conn.commit()
                
                logger.info("Created embedding record", 
                          embedding_id=new_id, 
                          document_type=document_type,
                          dimension=len(embedding))
                
            conn.close()
            return True
            
        except Exception as e:
            logger.error("Failed to create embedding record", 
                        error=str(e), 
                        document_type=document_type)
            return False

    def search_similar_documents(self, query: str, document_type: str = None, 
                                threshold: float = 0.7, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for similar documents using vector similarity
        
        Educational Focus:
        - Vector similarity queries in PostgreSQL
        - Cosine distance calculations
        - Threshold filtering
        - Result ranking and formatting
        """
        try:
            # Generate query embedding
            query_embedding = self.embedding_generator.embed_user_question(query)
            embedding_str = f"[{','.join(map(str, query_embedding))}]"
            
            conn = psycopg2.connect(**self.connection_params)
            with conn.cursor(cursor_factory=RealDictCursor) as cur:
                # Build similarity search query
                base_query = """
                    SELECT 
                        id, document_type, document_id, content, metadata,
                        embedding_model, embedding_dimension,
                        1 - (embedding <=> %s::vector) as similarity,
                        created_at
                    FROM document_embeddings 
                    WHERE 1 - (embedding <=> %s::vector) > %s
                """
                
                params = [embedding_str, embedding_str, threshold]
                
                # Add document type filter if specified
                if document_type:
                    base_query += " AND document_type = %s"
                    params.append(document_type)
                
                # Order by similarity and limit results
                base_query += " ORDER BY embedding <=> %s::vector ASC LIMIT %s"
                params.extend([embedding_str, limit])
                
                cur.execute(base_query, params)
                results = cur.fetchall()
                
                # Format results
                formatted_results = []
                for row in results:
                    formatted_results.append({
                        'embedding_id': row['id'],
                        'document_type': row['document_type'],
                        'document_id': row['document_id'],
                        'content': row['content'],
                        'similarity': float(row['similarity']),
                        'metadata': json.loads(row['metadata']) if row['metadata'] else {},
                        'embedding_model': row['embedding_model'],
                        'created_at': row['created_at'].isoformat() if row['created_at'] else None
                    })
                
                logger.info("Vector similarity search completed", 
                          query_length=len(query),
                          results_count=len(formatted_results),
                          threshold=threshold)
                
            conn.close()
            return formatted_results
            
        except Exception as e:
            logger.error("Vector similarity search failed", 
                        error=str(e), 
                        query=query[:100])
            return []

    def bulk_create_embeddings(self, documents: List[Tuple[Document, str, int]], 
                             batch_size: int = 50) -> Dict[str, int]:
        """
        Bulk create embeddings for multiple documents
        
        Educational Focus:
        - Batch processing patterns
        - Transaction management
        - Progress tracking
        - Error isolation
        """
        stats = {'processed': 0, 'created': 0, 'skipped': 0, 'failed': 0}
        
        try:
            # Process in batches
            for i in range(0, len(documents), batch_size):
                batch = documents[i:i + batch_size]
                
                logger.info("Processing batch", 
                          batch_start=i, 
                          batch_size=len(batch),
                          total=len(documents))
                
                for document, doc_type, doc_id in batch:
                    stats['processed'] += 1
                    
                    try:
                        if self.create_embedding_record(document, doc_type, doc_id):
                            stats['created'] += 1
                        else:
                            stats['skipped'] += 1
                    except Exception as e:
                        logger.warning("Failed to process document", 
                                     error=str(e), 
                                     doc_type=doc_type, 
                                     doc_id=doc_id)
                        stats['failed'] += 1
                
                # Small delay between batches to avoid overwhelming the database
                import time
                time.sleep(0.1)
            
            logger.info("Bulk embedding creation completed", **stats)
            return stats
            
        except Exception as e:
            logger.error("Bulk embedding creation failed", error=str(e))
            return stats

    def get_langchain_vector_store(self) -> Optional[PGVector]:
        """
        Get LangChain PGVector instance for RAG chains
        
        Educational Focus:
        - LangChain integration patterns
        - Vector store abstraction
        - Configuration management
        """
        if not LANGCHAIN_PGVECTOR_AVAILABLE:
            logger.error("LangChain PGVector not available")
            return None
            
        try:
            if not self.vector_store:
                # Build connection string for LangChain PGVector
                connection_string = (
                    f"postgresql://{self.connection_params['user']}:"
                    f"{self.connection_params['password']}@"
                    f"{self.connection_params['host']}:"
                    f"{self.connection_params['port']}/"
                    f"{self.connection_params['database']}"
                )
                
                self.vector_store = PGVector(
                    connection_string=connection_string,
                    embedding_function=self.embedding_generator.embeddings,
                    collection_name="document_embeddings",
                    distance_strategy="cosine"
                )
                
                logger.info("LangChain PGVector store initialized")
            
            return self.vector_store
            
        except Exception as e:
            logger.error("Failed to initialize LangChain PGVector", error=str(e))
            return None

    def sync_with_laravel_data(self) -> Dict[str, Any]:
        """
        Synchronize vector store with Laravel complaint data
        
        Educational Focus:
        - Cross-platform data synchronization
        - ETL patterns for vector data
        - Progress tracking and reporting
        """
        try:
            stats = {
                'complaints_processed': 0,
                'embeddings_created': 0,
                'embeddings_skipped': 0,
                'errors': 0
            }
            
            conn = psycopg2.connect(**self.connection_params)
            with conn.cursor(cursor_factory=RealDictCursor) as cur:
                # Get complaints that don't have embeddings yet
                cur.execute("""
                    SELECT c.id, c.complaint_type, c.descriptor, c.borough, 
                           c.incident_address, c.agency_name, c.status,
                           c.submitted_at, a.summary, a.category, a.tags
                    FROM complaints c
                    LEFT JOIN complaint_analyses a ON c.id = a.complaint_id
                    LEFT JOIN document_embeddings de ON (
                        de.document_type = 'complaint' AND de.document_id = c.id
                    )
                    WHERE de.id IS NULL
                    ORDER BY c.id
                    LIMIT 1000
                """)
                
                complaints = cur.fetchall()
                
                logger.info("Found complaints without embeddings", count=len(complaints))
                
                # Process each complaint
                documents = []
                for complaint in complaints:
                    stats['complaints_processed'] += 1
                    
                    # Format complaint content for embedding
                    content_parts = [
                        f"COMPLAINT TYPE: {complaint['complaint_type']}",
                        f"DESCRIPTION: {complaint['descriptor']}",
                        f"LOCATION: {complaint['borough']}, {complaint['incident_address']}",
                        f"AGENCY: {complaint['agency_name']}",
                        f"STATUS: {complaint['status']}",
                    ]
                    
                    if complaint['summary']:
                        content_parts.append(f"AI SUMMARY: {complaint['summary']}")
                    
                    if complaint['category']:
                        content_parts.append(f"CATEGORY: {complaint['category']}")
                    
                    content = "\n".join(content_parts)
                    
                    # Create document with metadata
                    metadata = {
                        'complaint_id': complaint['id'],
                        'borough': complaint['borough'],
                        'complaint_type': complaint['complaint_type'],
                        'agency': complaint['agency_name'],
                        'status': complaint['status'],
                        'submitted_at': complaint['submitted_at'].isoformat() if complaint['submitted_at'] else None,
                        'has_analysis': bool(complaint['summary'])
                    }
                    
                    document = Document(page_content=content, metadata=metadata)
                    documents.append((document, 'complaint', complaint['id']))
                
            conn.close()
            
            # Bulk create embeddings
            if documents:
                bulk_stats = self.bulk_create_embeddings(documents)
                stats.update({
                    'embeddings_created': bulk_stats['created'],
                    'embeddings_skipped': bulk_stats['skipped'],
                    'errors': bulk_stats['failed']
                })
            
            logger.info("Laravel data sync completed", **stats)
            return stats
            
        except Exception as e:
            logger.error("Laravel data sync failed", error=str(e))
            return {'error': str(e)}

    def get_statistics(self) -> Dict[str, Any]:
        """Get vector store statistics"""
        try:
            conn = psycopg2.connect(**self.connection_params)
            with conn.cursor(cursor_factory=RealDictCursor) as cur:
                # Get basic statistics
                cur.execute("""
                    SELECT 
                        COUNT(*) as total_embeddings,
                        COUNT(DISTINCT document_type) as document_types,
                        COUNT(DISTINCT embedding_model) as embedding_models,
                        AVG(embedding_dimension) as avg_dimension
                    FROM document_embeddings
                """)
                stats = cur.fetchone()
                
                # Get breakdown by document type
                cur.execute("""
                    SELECT document_type, COUNT(*) as count
                    FROM document_embeddings
                    GROUP BY document_type
                    ORDER BY count DESC
                """)
                type_breakdown = {row['document_type']: row['count'] for row in cur.fetchall()}
                
                # Get breakdown by embedding model
                cur.execute("""
                    SELECT embedding_model, COUNT(*) as count
                    FROM document_embeddings
                    GROUP BY embedding_model
                    ORDER BY count DESC
                """)
                model_breakdown = {row['embedding_model']: row['count'] for row in cur.fetchall()}
                
            conn.close()
            
            return {
                'total_embeddings': stats['total_embeddings'],
                'document_types': stats['document_types'],
                'embedding_models': stats['embedding_models'],
                'average_dimension': float(stats['avg_dimension']) if stats['avg_dimension'] else 0,
                'type_breakdown': type_breakdown,
                'model_breakdown': model_breakdown,
                'pgvector_available': True,
                'connection_healthy': True
            }
            
        except Exception as e:
            logger.error("Failed to get statistics", error=str(e))
            return {
                'error': str(e),
                'pgvector_available': PSYCOPG2_AVAILABLE,
                'connection_healthy': False
            }