# LaraCity Dashboard Features

The LaraCity Dashboard provides a comprehensive interface for managing and analyzing municipal complaints with integrated AI assistance.

## 🎯 Features Overview

### 📊 Complaints Management Table
- **Real-time Data**: Live table with all complaints and their current status
- **Advanced Filtering**: Filter by status, borough, risk level, and complaint type
- **Powerful Search**: Full-text search across complaint numbers, types, and descriptions
- **Smart Sorting**: Sort by any column including creation date, risk score, and status
- **Pagination**: Efficient handling of large datasets with customizable page sizes

### 🤖 AI Chat Assistant
- **Natural Language Queries**: Ask questions in plain English about complaints data
- **Semantic Search Integration**: Find similar complaints using AI-powered vector search
- **Real-time Responses**: Instant responses with complaint analysis and insights
- **Contextual Understanding**: AI understands borough names, complaint types, and risk levels
- **Keyboard Shortcuts**: Use Ctrl+Enter (Cmd+Enter on Mac) to send messages quickly

### 📈 Analytics Dashboard
- **Key Metrics**: At-a-glance view of total, open, escalated, and closed complaints
- **Risk Distribution**: Visual indicators for high, medium, and low-risk complaints
- **Status Tracking**: Real-time status updates and progress monitoring
- **Performance Indicators**: Success rates and processing metrics

## 🎮 How to Use

### Complaints Table
1. **Search**: Use the search box to find specific complaints by number, type, or content
2. **Filter**: Apply filters to narrow down results by:
   - Status (Open, In Progress, Escalated, Closed)
   - Borough (Manhattan, Brooklyn, Queens, Bronx, Staten Island)
   - Risk Level (High ≥0.7, Medium 0.4-0.7, Low <0.4)
3. **Sort**: Click any column header to sort data
4. **View Details**: Use the action menu (⋯) to view complaint details or find similar complaints

### AI Chat Assistant
Try these example queries:
- "Show me high-risk complaints in Manhattan"
- "Find heating complaints in Brooklyn"
- "What are the most common complaint types?"
- "How many escalated complaints are open?"
- "Search for water leak issues"
- "Find complaints from last week"

### Mobile Experience
- **Responsive Design**: Full functionality on desktop, tablet, and mobile
- **Mobile Chat Modal**: Dedicated chat interface for mobile users
- **Touch-Optimized**: All interactions optimized for touch devices

## 🔧 Technical Features

### Performance Optimizations
- **Livewire Components**: Reactive components for real-time updates
- **Efficient Queries**: Optimized database queries with eager loading
- **Smart Pagination**: Client-side pagination for smooth navigation
- **Debounced Search**: Reduces server load with intelligent search delays

### AI Integration
- **Hybrid Search**: Combines semantic search with metadata filtering
- **Vector Similarity**: Uses pgvector for finding similar complaints
- **LangChain Integration**: Advanced AI processing via Python bridge
- **Contextual Responses**: AI understands complaint patterns and relationships

### User Experience
- **Dark Mode Support**: Automatic theme adaptation
- **Accessibility**: Full keyboard navigation and screen reader support
- **Loading States**: Clear visual feedback during data processing
- **Error Handling**: Graceful error recovery with user-friendly messages

## 🎨 UI Components

### Built with Flux UI
- **Modern Design**: Clean, professional interface using Flux components
- **Consistent Styling**: Unified design language across all components
- **Interactive Elements**: Smooth animations and transitions
- **Flexible Layout**: Responsive grid system for all screen sizes

### Color Coding
- **Risk Levels**: 
  - 🔴 High Risk (≥0.7): Red badges and indicators
  - 🟡 Medium Risk (0.4-0.7): Yellow badges and indicators  
  - 🟢 Low Risk (<0.4): Green badges and indicators
- **Status Types**:
  - 🟢 Open: Green badges
  - 🔵 In Progress: Blue badges
  - 🔴 Escalated: Red badges
  - ⚪ Closed: Gray badges

## 📱 Responsive Behavior

### Desktop (≥1024px)
- **Two-Column Layout**: Complaints table (2/3) + Chat assistant (1/3)
- **Full Feature Set**: All functionality available
- **Keyboard Shortcuts**: Enhanced productivity features

### Tablet (768px - 1023px)
- **Adaptive Layout**: Table adjusts to available space
- **Chat Modal**: Floating chat interface
- **Touch Optimized**: Larger touch targets

### Mobile (<768px)
- **Single Column**: Full-width table with horizontal scroll
- **Mobile Chat Button**: Fixed floating button for chat access
- **Simplified Filters**: Collapsible filter interface

## 🚀 Getting Started

1. **Login**: Access the dashboard at `/dashboard` after authentication
2. **Explore Data**: Browse the complaints table to understand the data structure
3. **Try Filtering**: Use the filter options to narrow down results
4. **Ask the AI**: Use the chat assistant to explore data with natural language
5. **Discover Patterns**: Look for trends in risk scores and complaint types

## 💡 Tips for Effective Use

### Search Tips
- Use specific keywords for better results
- Combine filters for precise data sets
- Try different date ranges for trend analysis

### Chat Assistant Tips
- Be specific about what you're looking for
- Use borough names and complaint types in queries
- Ask follow-up questions to dive deeper into data
- Use natural language - the AI understands context

### Performance Tips
- Use pagination for large datasets
- Apply filters before searching large result sets
- Clear filters when switching between different analyses

## 🔮 Future Enhancements

Planned features for future releases:
- **Data Visualization**: Charts and graphs for trend analysis
- **Export Functionality**: Download filtered results as CSV/Excel
- **Advanced AI Features**: Predictive analytics and pattern recognition
- **Real-time Notifications**: Live updates for new high-risk complaints
- **Collaborative Features**: Notes and comments on complaints
- **API Integration**: Direct API access from the dashboard

---

The LaraCity Dashboard represents a modern approach to municipal data management, combining traditional data management with cutting-edge AI assistance for enhanced productivity and insights.