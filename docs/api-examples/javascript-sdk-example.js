/**
 * LaraCity JavaScript SDK Example
 * 
 * This module demonstrates how to integrate with the LaraCity API using JavaScript.
 * Includes examples for Node.js, browser environments, and modern frameworks.
 * 
 * Educational Focus:
 * - Promise-based HTTP client with fetch/axios
 * - Authentication token management
 * - Error handling and retry patterns
 * - TypeScript type definitions
 * - Modern async/await patterns
 * 
 * Installation:
 *   npm install axios dotenv
 * 
 * Usage:
 *   node javascript-sdk-example.js
 */

const axios = require('axios');
require('dotenv').config();

/**
 * Configuration for LaraCity API client
 */
class LaracityConfig {
    constructor(options = {}) {
        this.baseURL = options.baseURL || process.env.LARACITY_API_URL || 'http://laracity.test/api';
        this.apiToken = options.apiToken || process.env.LARACITY_API_TOKEN;
        this.timeout = options.timeout || 30000;
        this.maxRetries = options.maxRetries || 3;
        this.retryDelay = options.retryDelay || 1000;
    }
}

/**
 * Custom error class for LaraCity API errors
 */
class LaracityAPIError extends Error {
    constructor(message, statusCode = null, responseData = {}) {
        super(message);
        this.name = 'LaracityAPIError';
        this.statusCode = statusCode;
        this.responseData = responseData;
    }
}

/**
 * LaraCity API Client
 * 
 * Provides a complete interface for interacting with the LaraCity
 * complaint management and AI search API.
 */
class LaracityClient {
    constructor(config = {}) {
        this.config = new LaracityConfig(config);
        
        // Create axios instance with default configuration
        this.client = axios.create({
            baseURL: this.config.baseURL,
            timeout: this.config.timeout,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        // Set authorization header if token is available
        if (this.config.apiToken) {
            this.client.defaults.headers.common['Authorization'] = `Bearer ${this.config.apiToken}`;
        }
        
        // Add response interceptor for error handling
        this.client.interceptors.response.use(
            response => response,
            error => this._handleError(error)
        );
    }
    
    /**
     * Authenticate with the API and get an access token
     */
    async authenticate(email, password, deviceName = 'javascript-sdk') {
        try {
            const authURL = this.config.baseURL.replace('/api', '/sanctum/token');
            
            const response = await axios.post(authURL, {
                email,
                password,
                device_name: deviceName
            });
            
            const { token } = response.data;
            this.config.apiToken = token;
            this.client.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            
            console.log(`✅ Authentication successful for ${email}`);
            return token;
            
        } catch (error) {
            throw new LaracityAPIError(
                `Authentication failed: ${error.message}`,
                error.response?.status,
                error.response?.data
            );
        }
    }
    
    /**
     * Handle HTTP errors with retry logic
     */
    async _handleError(error) {
        const { config: requestConfig, response } = error;
        
        // Check if we should retry
        if (response?.status === 429 && requestConfig.__retryCount < this.config.maxRetries) {
            requestConfig.__retryCount = (requestConfig.__retryCount || 0) + 1;
            
            const delay = this.config.retryDelay * Math.pow(2, requestConfig.__retryCount - 1);
            console.warn(`Rate limited, retrying in ${delay}ms...`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
            return this.client.request(requestConfig);
        }
        
        // Create detailed error message
        const message = response?.data?.message || error.message;
        const statusCode = response?.status;
        const responseData = response?.data || {};
        
        throw new LaracityAPIError(message, statusCode, responseData);
    }
    
    // ================== Complaint Management ==================
    
    /**
     * Get paginated list of complaints with filtering
     */
    async getComplaints(options = {}) {
        const {
            page = 1,
            perPage = 10,
            borough,
            status,
            complaintType,
            riskLevel,
            dateFrom,
            dateTo,
            sortBy = 'created_date',
            sortOrder = 'desc'
        } = options;
        
        const params = {
            page,
            per_page: Math.min(perPage, 100),
            sort_by: sortBy,
            sort_order: sortOrder
        };
        
        // Add optional filters
        if (borough) params.borough = borough;
        if (status) params.status = status;
        if (complaintType) params.complaint_type = complaintType;
        if (riskLevel) params.risk_level = riskLevel;
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        
        const response = await this.client.get('/complaints', { params });
        return response.data;
    }
    
    /**
     * Get detailed information about a specific complaint
     */
    async getComplaint(complaintId) {
        const response = await this.client.get(`/complaints/${complaintId}`);
        return response.data;
    }
    
    /**
     * Get aggregated complaint statistics
     */
    async getComplaintsSummary(options = {}) {
        const { borough, dateRange } = options;
        
        const params = {};
        if (borough) params.borough = borough;
        if (dateRange) params.date_range = dateRange;
        
        const response = await this.client.get('/complaints/summary', { params });
        return response.data;
    }
    
    // ================== Semantic Search ==================
    
    /**
     * Perform hybrid semantic search
     */
    async semanticSearch(query, options = {}) {
        const payload = { query };
        
        if (options.filters) payload.filters = options.filters;
        if (options.searchOptions) payload.options = options.searchOptions;
        
        const response = await this.client.post('/search/semantic', payload);
        return response.data;
    }
    
    /**
     * Perform pure vector similarity search
     */
    async similaritySearch(query, options = {}) {
        const {
            limit = 10,
            similarityThreshold = 0.7
        } = options;
        
        const payload = {
            query,
            limit,
            similarity_threshold: similarityThreshold
        };
        
        const response = await this.client.post('/search/similar', payload);
        return response.data;
    }
    
    /**
     * Generate vector embeddings for text
     */
    async generateEmbeddings(text, includeMetadata = true) {
        const payload = {
            text,
            include_metadata: includeMetadata
        };
        
        const response = await this.client.post('/search/embed', payload);
        return response.data;
    }
    
    /**
     * Get vector store statistics
     */
    async getSearchStats() {
        const response = await this.client.get('/search/stats');
        return response.data;
    }
    
    /**
     * Test search system health
     */
    async testSearchSystem() {
        const response = await this.client.get('/search/test');
        return response.data;
    }
    
    // ================== Actions ==================
    
    /**
     * Escalate multiple complaints
     */
    async escalateComplaints(complaintIds, options = {}) {
        const {
            reason,
            priority = 'high',
            notifySlack = true,
            assignTo
        } = options;
        
        const payload = {
            complaint_ids: complaintIds,
            reason,
            priority,
            notify_slack: notifySlack
        };
        
        if (assignTo) payload.assign_to = assignTo;
        
        const response = await this.client.post('/actions/escalate', payload);
        return response.data;
    }
    
    /**
     * Log natural language question
     */
    async logUserQuestion(question, options = {}) {
        const {
            context = '',
            userSession,
            metadata
        } = options;
        
        const payload = {
            question,
            context
        };
        
        if (userSession) payload.user_session = userSession;
        if (metadata) payload.metadata = metadata;
        
        const response = await this.client.post('/user-questions', payload);
        return response.data;
    }
    
    /**
     * Check system health
     */
    async healthCheck() {
        const response = await this.client.get('/health');
        return response.data;
    }
}

// ================== Browser-Compatible Version ==================

/**
 * Browser-compatible LaraCity client using fetch API
 */
class LaracityBrowserClient {
    constructor(config = {}) {
        this.config = new LaracityConfig(config);
    }
    
    async _fetch(endpoint, options = {}) {
        const url = `${this.config.baseURL}/${endpoint.replace(/^\//, '')}`;
        
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (this.config.apiToken) {
            headers['Authorization'] = `Bearer ${this.config.apiToken}`;
        }
        
        const fetchOptions = {
            ...options,
            headers
        };
        
        if (options.body && typeof options.body === 'object') {
            fetchOptions.body = JSON.stringify(options.body);
        }
        
        try {
            const response = await fetch(url, fetchOptions);
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new LaracityAPIError(
                    `HTTP ${response.status}: ${response.statusText}`,
                    response.status,
                    errorData
                );
            }
            
            return await response.json();
            
        } catch (error) {
            if (error instanceof LaracityAPIError) {
                throw error;
            }
            throw new LaracityAPIError(`Request failed: ${error.message}`);
        }
    }
    
    async semanticSearch(query, options = {}) {
        return this._fetch('/search/semantic', {
            method: 'POST',
            body: { query, ...options }
        });
    }
    
    async getComplaints(options = {}) {
        const params = new URLSearchParams(options).toString();
        const endpoint = params ? `/complaints?${params}` : '/complaints';
        return this._fetch(endpoint);
    }
}

// ================== Demo Functions ==================

async function demoComplaintManagement() {
    console.log('🔧 LaraCity JavaScript SDK - Complaint Management Demo');
    console.log('='.repeat(60));
    
    const client = new LaracityClient();
    
    // Authenticate if no token provided
    if (!client.config.apiToken) {
        try {
            await client.authenticate(
                process.env.LARACITY_EMAIL || 'demo@laracity.local',
                process.env.LARACITY_PASSWORD || 'demo-password'
            );
        } catch (error) {
            console.error('❌ Authentication failed:', error.message);
            return;
        }
    }
    
    try {
        // Get complaints summary
        console.log('\n📊 Getting complaints summary...');
        const summary = await client.getComplaintsSummary();
        console.log(`Total complaints: ${summary.total_complaints || 'N/A'}`);
        console.log(`By status:`, summary.by_status || {});
        
        // Search for complaints
        console.log('\n🔍 Searching for complaints...');
        const complaints = await client.getComplaints({
            borough: 'MANHATTAN',
            perPage: 5,
            sortBy: 'created_date'
        });
        
        console.log(`Found ${complaints.total || 0} complaints in Manhattan`);
        (complaints.data || []).slice(0, 3).forEach(complaint => {
            console.log(`  • #${complaint.complaint_number}: ${complaint.complaint_type}`);
        });
        
        // Semantic search
        console.log('\n🧠 Performing semantic search...');
        const searchResults = await client.semanticSearch(
            'heating problems apartment building',
            {
                filters: { borough: 'BROOKLYN' },
                searchOptions: { limit: 5 }
            }
        );
        
        const resultsCount = (searchResults.results || []).length;
        console.log(`Found ${resultsCount} semantically similar complaints`);
        
        (searchResults.results || []).slice(0, 2).forEach(result => {
            const score = result.combined_score || 0;
            const complaint = result.complaint || {};
            console.log(`  • Score: ${score.toFixed(3)} - ${complaint.complaint_type || 'N/A'}`);
        });
        
        // Health check
        console.log('\n🏥 Checking system health...');
        const health = await client.healthCheck();
        console.log(`System status: ${health.status || 'unknown'}`);
        
    } catch (error) {
        console.error('❌ Demo failed:', error.message);
        if (error.responseData) {
            console.error('Response data:', JSON.stringify(error.responseData, null, 2));
        }
    }
}

async function demoConcurrentOperations() {
    console.log('\n⚡ Concurrent Operations Demo');
    console.log('='.repeat(30));
    
    const client = new LaracityClient();
    
    const searchQueries = [
        'heating problems winter apartment',
        'noise complaints late night music',
        'water leak ceiling damage',
        'street repair pothole damage'
    ];
    
    console.log(`Performing ${searchQueries.length} concurrent searches...`);
    const startTime = Date.now();
    
    try {
        // Run searches concurrently
        const searchPromises = searchQueries.map(query =>
            client.semanticSearch(query, { searchOptions: { limit: 3 } })
        );
        
        const results = await Promise.allSettled(searchPromises);
        
        const elapsed = (Date.now() - startTime) / 1000;
        console.log(`✅ Completed ${results.length} searches in ${elapsed.toFixed(2)}s`);
        
        results.forEach((result, index) => {
            if (result.status === 'fulfilled') {
                const count = (result.value.results || []).length;
                console.log(`  Search ${index + 1}: ${count} results for '${searchQueries[index].substring(0, 30)}...'`);
            } else {
                console.log(`  Search ${index + 1}: Error - ${result.reason.message}`);
            }
        });
        
    } catch (error) {
        console.error('❌ Concurrent operations failed:', error.message);
    }
}

async function demoErrorHandling() {
    console.log('\n🛡️  Error Handling Demo');
    console.log('='.repeat(25));
    
    const client = new LaracityClient({
        apiToken: 'invalid-token-for-demo'
    });
    
    try {
        // This should fail with authentication error
        await client.getComplaints();
    } catch (error) {
        console.log('✅ Caught API error:', error.message);
        console.log('   Status code:', error.statusCode);
        console.log('   Response data:', error.responseData);
    }
}

async function demoBulkOperations() {
    console.log('\n📦 Bulk Operations Demo');
    console.log('='.repeat(25));
    
    const client = new LaracityClient();
    
    try {
        // Get multiple pages of complaints
        const allComplaints = [];
        
        for (let page = 1; page <= 3; page++) {
            const complaintsPage = await client.getComplaints({ 
                page, 
                perPage: 10 
            });
            
            allComplaints.push(...(complaintsPage.data || []));
            console.log(`Loaded page ${page}: ${(complaintsPage.data || []).length} complaints`);
        }
        
        console.log(`Total complaints loaded: ${allComplaints.length}`);
        
        // Find high-risk complaints
        const highRiskComplaints = allComplaints.filter(complaint => {
            const analysis = complaint.analysis || {};
            return (analysis.risk_score || 0) >= 0.7;
        });
        
        if (highRiskComplaints.length > 0) {
            console.log(`Found ${highRiskComplaints.length} high-risk complaints`);
            
            // Escalate in batches
            const batchSize = 5;
            const complaintIds = highRiskComplaints.map(c => c.id);
            
            for (let i = 0; i < complaintIds.length; i += batchSize) {
                const batch = complaintIds.slice(i, i + batchSize);
                
                const escalationResult = await client.escalateComplaints(batch, {
                    reason: 'Automated escalation - high risk threshold exceeded',
                    priority: 'high'
                });
                
                console.log(`Escalated batch ${Math.floor(i / batchSize) + 1}: ${escalationResult.escalated_count || 0} complaints`);
            }
        }
        
    } catch (error) {
        console.error('❌ Bulk operation failed:', error.message);
    }
}

// ================== TypeScript Type Definitions ==================

/**
 * TypeScript definitions for better development experience
 * Save this as laracity-types.d.ts in your project
 */
const typeDefinitions = `
interface LaracityConfig {
    baseURL?: string;
    apiToken?: string;
    timeout?: number;
    maxRetries?: number;
    retryDelay?: number;
}

interface Complaint {
    id: number;
    complaint_number: string;
    complaint_type: string;
    complaint_description: string;
    borough: string;
    status: string;
    priority: string;
    created_date: string;
    analysis?: ComplaintAnalysis;
}

interface ComplaintAnalysis {
    risk_score: number;
    category: string;
    sentiment: string;
    priority: string;
    tags: string[];
    summary: string;
}

interface SearchResult {
    complaint: Complaint;
    content: string;
    combined_score: number;
    similarity_score: number;
}

interface SearchResponse {
    results: SearchResult[];
    search_metadata: {
        method: string;
        total_time_ms: number;
        vector_search_time_ms?: number;
    };
}

interface PaginatedResponse<T> {
    data: T[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}
`;

// ================== React Hook Example ==================

const reactHookExample = `
// Custom React hook for LaraCity API
import { useState, useEffect } from 'react';

export function useLaracityComplaints(filters = {}) {
    const [complaints, setComplaints] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    
    const client = new LaracityClient();
    
    useEffect(() => {
        const fetchComplaints = async () => {
            setLoading(true);
            setError(null);
            
            try {
                const response = await client.getComplaints(filters);
                setComplaints(response.data || []);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };
        
        fetchComplaints();
    }, [JSON.stringify(filters)]);
    
    return { complaints, loading, error };
}

// Usage in React component
function ComplaintsList() {
    const { complaints, loading, error } = useLaracityComplaints({
        borough: 'MANHATTAN',
        status: 'Open'
    });
    
    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;
    
    return (
        <ul>
            {complaints.map(complaint => (
                <li key={complaint.id}>
                    #{complaint.complaint_number}: {complaint.complaint_type}
                </li>
            ))}
        </ul>
    );
}
`;

// ================== Main Execution ==================

async function main() {
    console.log('🚀 LaraCity JavaScript SDK Examples');
    console.log('====================================\n');
    
    try {
        await demoComplaintManagement();
        await demoErrorHandling();
        await demoBulkOperations();
        await demoConcurrentOperations();
        
        console.log('\n✅ All demos completed!');
        console.log('\n📚 Next steps:');
        console.log('   • Set your API token: export LARACITY_API_TOKEN="your-token"');
        console.log('   • Install TypeScript definitions for better development');
        console.log('   • Use the React hook example for frontend integration');
        console.log('   • See docs/api-examples/ for more examples');
        
    } catch (error) {
        console.error('Demo execution failed:', error);
    }
}

// Export classes for use as module
module.exports = {
    LaracityClient,
    LaracityBrowserClient,
    LaracityAPIError,
    LaracityConfig
};

// Run demos if script is executed directly
if (require.main === module) {
    main();
}
`;

console.log(typeDefinitions);
console.log(reactHookExample);