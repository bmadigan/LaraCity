#!/usr/bin/env python3
"""
LaraCity Python SDK Example

This script demonstrates how to integrate with the LaraCity API using Python.
It includes examples for authentication, complaint management, semantic search,
and error handling patterns.

Educational Focus:
- REST API integration patterns
- Authentication with Bearer tokens
- Error handling and retry logic
- Async/await patterns for performance
- Type hints for better code quality

Installation:
    pip install requests aiohttp python-dotenv

Usage:
    python python-sdk-example.py
"""

import asyncio
import json
import logging
import os
import time
from dataclasses import dataclass
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any, Union
from urllib.parse import urljoin

import aiohttp
import requests
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@dataclass
class LaracityConfig:
    """Configuration for LaraCity API client."""
    base_url: str = "http://laracity.test/api"
    api_token: Optional[str] = None
    timeout: int = 30
    max_retries: int = 3
    retry_delay: float = 1.0


class LaracityAPIError(Exception):
    """Custom exception for LaraCity API errors."""
    
    def __init__(self, message: str, status_code: int = None, response_data: Dict = None):
        super().__init__(message)
        self.status_code = status_code
        self.response_data = response_data or {}


class LaracityClient:
    """
    Synchronous client for LaraCity API.
    
    This class provides a convenient interface for interacting with
    the LaraCity complaint management and AI search API.
    """
    
    def __init__(self, config: LaracityConfig):
        self.config = config
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
        
        if config.api_token:
            self.session.headers['Authorization'] = f'Bearer {config.api_token}'
    
    def authenticate(self, email: str, password: str, device_name: str = "python-sdk") -> str:
        """
        Authenticate with the API and get an access token.
        
        Args:
            email: User email
            password: User password  
            device_name: Device identifier for the token
            
        Returns:
            API token string
            
        Raises:
            LaracityAPIError: If authentication fails
        """
        auth_url = self.config.base_url.replace('/api', '/sanctum/token')
        
        response = self.session.post(auth_url, json={
            'email': email,
            'password': password,
            'device_name': device_name
        })
        
        if response.status_code == 200:
            data = response.json()
            token = data['token']
            self.config.api_token = token
            self.session.headers['Authorization'] = f'Bearer {token}'
            logger.info(f"Authentication successful for {email}")
            return token
        else:
            raise LaracityAPIError(
                f"Authentication failed: {response.text}",
                response.status_code,
                response.json() if response.headers.get('content-type', '').startswith('application/json') else {}
            )
    
    def _make_request(self, method: str, endpoint: str, **kwargs) -> Dict[str, Any]:
        """Make HTTP request with retry logic and error handling."""
        url = urljoin(self.config.base_url + '/', endpoint.lstrip('/'))
        
        for attempt in range(self.config.max_retries):
            try:
                response = self.session.request(
                    method, 
                    url, 
                    timeout=self.config.timeout,
                    **kwargs
                )
                
                if response.status_code < 400:
                    return response.json() if response.content else {}
                elif response.status_code == 429:  # Rate limit
                    if attempt < self.config.max_retries - 1:
                        sleep_time = self.config.retry_delay * (2 ** attempt)
                        logger.warning(f"Rate limited, retrying in {sleep_time}s...")
                        time.sleep(sleep_time)
                        continue
                
                # Handle HTTP errors
                error_data = {}
                try:
                    error_data = response.json()
                except:
                    pass
                
                raise LaracityAPIError(
                    f"HTTP {response.status_code}: {response.text}",
                    response.status_code,
                    error_data
                )
                
            except requests.RequestException as e:
                if attempt < self.config.max_retries - 1:
                    logger.warning(f"Request failed, retrying: {e}")
                    time.sleep(self.config.retry_delay)
                    continue
                raise LaracityAPIError(f"Request failed: {e}")
        
        raise LaracityAPIError("Max retries exceeded")
    
    # Complaint Management Methods
    
    def get_complaints(self, 
                      page: int = 1,
                      per_page: int = 10,
                      borough: Optional[str] = None,
                      status: Optional[str] = None,
                      complaint_type: Optional[str] = None,
                      risk_level: Optional[str] = None,
                      date_from: Optional[str] = None,
                      date_to: Optional[str] = None,
                      sort_by: str = 'created_date',
                      sort_order: str = 'desc') -> Dict[str, Any]:
        """
        Get paginated list of complaints with filtering.
        
        Args:
            page: Page number (default: 1)
            per_page: Items per page (default: 10, max: 100)
            borough: Filter by borough (MANHATTAN, BROOKLYN, etc.)
            status: Filter by status (Open, Closed, etc.)
            complaint_type: Filter by complaint type
            risk_level: Filter by risk level (low, medium, high)
            date_from: Filter from date (YYYY-MM-DD)
            date_to: Filter to date (YYYY-MM-DD)
            sort_by: Sort field (created_date, risk_score, complaint_type)
            sort_order: Sort order (asc, desc)
            
        Returns:
            Paginated complaint data with metadata
        """
        params = {
            'page': page,
            'per_page': min(per_page, 100),
            'sort_by': sort_by,
            'sort_order': sort_order
        }
        
        # Add optional filters
        if borough:
            params['borough'] = borough
        if status:
            params['status'] = status
        if complaint_type:
            params['complaint_type'] = complaint_type
        if risk_level:
            params['risk_level'] = risk_level
        if date_from:
            params['date_from'] = date_from
        if date_to:
            params['date_to'] = date_to
        
        return self._make_request('GET', '/complaints', params=params)
    
    def get_complaint(self, complaint_id: int) -> Dict[str, Any]:
        """Get detailed information about a specific complaint."""
        return self._make_request('GET', f'/complaints/{complaint_id}')
    
    def get_complaints_summary(self, 
                              borough: Optional[str] = None,
                              date_range: Optional[int] = None) -> Dict[str, Any]:
        """
        Get aggregated complaint statistics.
        
        Args:
            borough: Filter by specific borough
            date_range: Number of days to include (default: 30)
            
        Returns:
            Summary statistics and aggregations
        """
        params = {}
        if borough:
            params['borough'] = borough
        if date_range:
            params['date_range'] = date_range
            
        return self._make_request('GET', '/complaints/summary', params=params)
    
    # Semantic Search Methods
    
    def semantic_search(self,
                       query: str,
                       filters: Optional[Dict[str, Any]] = None,
                       options: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """
        Perform hybrid semantic search combining vector similarity and metadata filtering.
        
        Args:
            query: Natural language search query
            filters: Metadata filters (borough, status, etc.)
            options: Search options (weights, thresholds, limits)
            
        Returns:
            Search results with similarity scores and metadata
        """
        payload = {'query': query}
        
        if filters:
            payload['filters'] = filters
        if options:
            payload['options'] = options
            
        return self._make_request('POST', '/search/semantic', json=payload)
    
    def similarity_search(self,
                         query: str,
                         limit: int = 10,
                         similarity_threshold: float = 0.7) -> Dict[str, Any]:
        """
        Perform pure vector similarity search.
        
        Args:
            query: Text to find similar content for
            limit: Maximum number of results
            similarity_threshold: Minimum similarity score
            
        Returns:
            Similar documents with similarity scores
        """
        payload = {
            'query': query,
            'limit': limit,
            'similarity_threshold': similarity_threshold
        }
        
        return self._make_request('POST', '/search/similar', json=payload)
    
    def generate_embeddings(self, 
                           text: str,
                           include_metadata: bool = True) -> Dict[str, Any]:
        """Generate vector embeddings for given text."""
        payload = {
            'text': text,
            'include_metadata': include_metadata
        }
        
        return self._make_request('POST', '/search/embed', json=payload)
    
    def get_search_stats(self) -> Dict[str, Any]:
        """Get vector store statistics and health information."""
        return self._make_request('GET', '/search/stats')
    
    def test_search_system(self) -> Dict[str, Any]:
        """Test search system health and connectivity."""
        return self._make_request('GET', '/search/test')
    
    # Action Methods
    
    def escalate_complaints(self,
                           complaint_ids: List[int],
                           reason: str,
                           priority: str = 'high',
                           notify_slack: bool = True,
                           assign_to: Optional[str] = None) -> Dict[str, Any]:
        """
        Escalate multiple complaints.
        
        Args:
            complaint_ids: List of complaint IDs to escalate
            reason: Escalation reason
            priority: Priority level (low, medium, high, urgent)
            notify_slack: Whether to send Slack notifications
            assign_to: Team or person to assign to
            
        Returns:
            Escalation results and action details
        """
        payload = {
            'complaint_ids': complaint_ids,
            'reason': reason,
            'priority': priority,
            'notify_slack': notify_slack
        }
        
        if assign_to:
            payload['assign_to'] = assign_to
            
        return self._make_request('POST', '/actions/escalate', json=payload)
    
    # User Questions
    
    def log_user_question(self,
                         question: str,
                         context: str = '',
                         user_session: Optional[str] = None,
                         metadata: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Log natural language question for analytics."""
        payload = {
            'question': question,
            'context': context
        }
        
        if user_session:
            payload['user_session'] = user_session
        if metadata:
            payload['metadata'] = metadata
            
        return self._make_request('POST', '/user-questions', json=payload)
    
    # System Health
    
    def health_check(self) -> Dict[str, Any]:
        """Check system health including all components."""
        return self._make_request('GET', '/health')


class AsyncLaracityClient:
    """
    Asynchronous client for LaraCity API.
    
    Provides async/await support for high-performance applications
    and concurrent API operations.
    """
    
    def __init__(self, config: LaracityConfig):
        self.config = config
        self.headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
        
        if config.api_token:
            self.headers['Authorization'] = f'Bearer {config.api_token}'
    
    async def _make_request(self, method: str, endpoint: str, **kwargs) -> Dict[str, Any]:
        """Async HTTP request with error handling."""
        url = urljoin(self.config.base_url + '/', endpoint.lstrip('/'))
        
        timeout = aiohttp.ClientTimeout(total=self.config.timeout)
        
        async with aiohttp.ClientSession(
            headers=self.headers,
            timeout=timeout
        ) as session:
            
            for attempt in range(self.config.max_retries):
                try:
                    async with session.request(method, url, **kwargs) as response:
                        response_data = await response.json() if response.content_length else {}
                        
                        if response.status < 400:
                            return response_data
                        elif response.status == 429:  # Rate limit
                            if attempt < self.config.max_retries - 1:
                                sleep_time = self.config.retry_delay * (2 ** attempt)
                                logger.warning(f"Rate limited, retrying in {sleep_time}s...")
                                await asyncio.sleep(sleep_time)
                                continue
                        
                        raise LaracityAPIError(
                            f"HTTP {response.status}: {await response.text()}",
                            response.status,
                            response_data
                        )
                        
                except aiohttp.ClientError as e:
                    if attempt < self.config.max_retries - 1:
                        logger.warning(f"Request failed, retrying: {e}")
                        await asyncio.sleep(self.config.retry_delay)
                        continue
                    raise LaracityAPIError(f"Request failed: {e}")
            
            raise LaracityAPIError("Max retries exceeded")
    
    async def semantic_search(self, query: str, **kwargs) -> Dict[str, Any]:
        """Async semantic search."""
        payload = {'query': query, **kwargs}
        return await self._make_request('POST', '/search/semantic', json=payload)
    
    async def get_complaints(self, **kwargs) -> Dict[str, Any]:
        """Async get complaints."""
        return await self._make_request('GET', '/complaints', params=kwargs)


# Example Usage and Demo Functions

def demo_complaint_management():
    """Demonstrate complaint management features."""
    print("🔧 LaraCity Python SDK - Complaint Management Demo")
    print("=" * 60)
    
    # Initialize client
    config = LaracityConfig(
        base_url=os.getenv('LARACITY_API_URL', 'http://laracity.test/api'),
        api_token=os.getenv('LARACITY_API_TOKEN')
    )
    
    client = LaracityClient(config)
    
    # Authenticate if no token provided
    if not config.api_token:
        try:
            token = client.authenticate(
                email=os.getenv('LARACITY_EMAIL', 'demo@laracity.local'),
                password=os.getenv('LARACITY_PASSWORD', 'demo-password')
            )
            print(f"✅ Authenticated successfully")
        except LaracityAPIError as e:
            print(f"❌ Authentication failed: {e}")
            return
    
    try:
        # Get complaints summary
        print("\n📊 Getting complaints summary...")
        summary = client.get_complaints_summary()
        print(f"Total complaints: {summary.get('total_complaints', 'N/A')}")
        print(f"By status: {summary.get('by_status', {})}")
        
        # Search for complaints
        print("\n🔍 Searching for complaints...")
        complaints = client.get_complaints(
            borough='MANHATTAN',
            per_page=5,
            sort_by='created_date'
        )
        
        print(f"Found {complaints.get('total', 0)} complaints in Manhattan")
        for complaint in complaints.get('data', [])[:3]:
            print(f"  • #{complaint['complaint_number']}: {complaint['complaint_type']}")
        
        # Semantic search example
        print("\n🧠 Performing semantic search...")
        search_results = client.semantic_search(
            query="heating problems apartment building",
            filters={'borough': 'BROOKLYN'},
            options={'limit': 5}
        )
        
        results_count = len(search_results.get('results', []))
        print(f"Found {results_count} semantically similar complaints")
        
        for result in search_results.get('results', [])[:2]:
            score = result.get('combined_score', 0)
            complaint = result.get('complaint', {})
            print(f"  • Score: {score:.3f} - {complaint.get('complaint_type', 'N/A')}")
        
        # Health check
        print("\n🏥 Checking system health...")
        health = client.health_check()
        print(f"System status: {health.get('status', 'unknown')}")
        
    except LaracityAPIError as e:
        print(f"❌ API Error: {e}")
        if e.response_data:
            print(f"Response data: {json.dumps(e.response_data, indent=2)}")


async def demo_async_operations():
    """Demonstrate async operations for high performance."""
    print("\n⚡ Async Operations Demo")
    print("=" * 30)
    
    config = LaracityConfig(
        api_token=os.getenv('LARACITY_API_TOKEN')
    )
    
    client = AsyncLaracityClient(config)
    
    # Concurrent searches
    search_queries = [
        "heating problems winter apartment",
        "noise complaints late night music",
        "water leak ceiling damage",
        "street repair pothole damage"
    ]
    
    print(f"Performing {len(search_queries)} concurrent searches...")
    start_time = time.time()
    
    try:
        # Run searches concurrently
        tasks = [
            client.semantic_search(query, options={'limit': 3})
            for query in search_queries
        ]
        
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        elapsed = time.time() - start_time
        print(f"✅ Completed {len(results)} searches in {elapsed:.2f}s")
        
        for i, result in enumerate(results):
            if isinstance(result, Exception):
                print(f"  Search {i+1}: Error - {result}")
            else:
                count = len(result.get('results', []))
                print(f"  Search {i+1}: {count} results for '{search_queries[i][:30]}...'")
    
    except Exception as e:
        print(f"❌ Async operation failed: {e}")


def demo_error_handling():
    """Demonstrate error handling patterns."""
    print("\n🛡️  Error Handling Demo")
    print("=" * 25)
    
    config = LaracityConfig(
        api_token="invalid-token-for-demo"
    )
    
    client = LaracityClient(config)
    
    try:
        # This should fail with authentication error
        client.get_complaints()
    except LaracityAPIError as e:
        print(f"✅ Caught API error: {e}")
        print(f"   Status code: {e.status_code}")
        print(f"   Response data: {e.response_data}")


def demo_bulk_operations():
    """Demonstrate bulk operations and batch processing."""
    print("\n📦 Bulk Operations Demo")
    print("=" * 25)
    
    config = LaracityConfig(
        api_token=os.getenv('LARACITY_API_TOKEN')
    )
    
    client = LaracityClient(config)
    
    try:
        # Get multiple pages of complaints
        all_complaints = []
        
        for page in range(1, 4):  # Get first 3 pages
            complaints_page = client.get_complaints(page=page, per_page=10)
            all_complaints.extend(complaints_page.get('data', []))
            
            print(f"Loaded page {page}: {len(complaints_page.get('data', []))} complaints")
        
        print(f"Total complaints loaded: {len(all_complaints)}")
        
        # Find high-risk complaints for escalation
        high_risk_ids = []
        for complaint in all_complaints:
            analysis = complaint.get('analysis', {})
            if analysis.get('risk_score', 0) >= 0.7:
                high_risk_ids.append(complaint['id'])
        
        if high_risk_ids:
            print(f"Found {len(high_risk_ids)} high-risk complaints")
            
            # Escalate in smaller batches
            batch_size = 5
            for i in range(0, len(high_risk_ids), batch_size):
                batch = high_risk_ids[i:i+batch_size]
                
                escalation_result = client.escalate_complaints(
                    complaint_ids=batch,
                    reason="Automated escalation - high risk threshold exceeded",
                    priority="high"
                )
                
                print(f"Escalated batch {i//batch_size + 1}: {escalation_result.get('escalated_count', 0)} complaints")
        
    except LaracityAPIError as e:
        print(f"❌ Bulk operation failed: {e}")


if __name__ == "__main__":
    print("🚀 LaraCity Python SDK Examples")
    print("================================\n")
    
    # Run synchronous demos
    demo_complaint_management()
    demo_error_handling()
    demo_bulk_operations()
    
    # Run async demo
    asyncio.run(demo_async_operations())
    
    print("\n✅ All demos completed!")
    print("\n📚 Next steps:")
    print("   • Set your API token: export LARACITY_API_TOKEN='your-token'")
    print("   • Customize the config for your environment")
    print("   • Integrate with your application workflow")
    print("   • See docs/api-examples/ for more examples")