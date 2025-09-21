# URL Shortener MVP - Project Plan

## Project Overview
A simple URL shortener service built with PHP, PostgreSQL, and Docker. This MVP focuses on core functionality with anonymous usage and REST API endpoints.

## Requirements

### Core Functionality
- **URL Shortening**: Convert long URLs to short 8-character codes
- **URL Redirection**: Redirect users from short URLs to original URLs
- **Auto-generated codes**: System generates random 8-character short codes
- **URL Support**: Accept HTTP and HTTPS URLs only
- **Anonymous usage**: No user authentication or accounts required
- **Frontend interface (REST API only)**

### Technical Stack
- **Backend**: Plain PHP (no framework)
- **Database**: PostgreSQL
- **Web Server**: Apache
- **Deployment**: Docker containers (separate containers for each service)
- **Hosting**: Self-hosted


## Architecture

### Project Structure
```
url-shortener/
├── docker-compose.yml
├── Dockerfile
├── apache/
│   └── 000-default.conf
├── src/
│   ├── index.php
│   ├── models/
│   │   └── Url.php
│   ├── controllers/
│   │   ├── ShortenController.php
│   │   └── RedirectController.php
│   ├── services/
│   │   ├── Database.php
│   │   ├── Request.php
│   │   └── Response.php
│   ├── config/
│   │   └── database.php
│   └── includes/
│       └── utils.php
└── sql/
    └── init.sql
```

### OOP Design

**Models:**
- `Url.php` - Model for the urls table with CRUD operations

**Controllers:**
- `ShortenController.php` - Handles POST /api/shorten endpoint
- `RedirectController.php` - Handles GET /{shortCode} redirect logic

**Services:**
- `Database.php` - Database connection and query handling service
- `Request.php` - HTTP request handling and parsing
- `Response.php` - HTTP response formatting and sending

**Config:**
- `database.php` - Database configuration settings

**Utils:**
- `utils.php` - Utility functions (URL validation, short code generation)

**Router:**
- `index.php` - Main router dispatching requests to appropriate controllers

## API Endpoints

### 1. Create Short URL
- **Method**: POST
- **Endpoint**: `/api/shorten`
- **Request Body**: JSON with `url` field
- **Response**: JSON with `short_url` and `original_url`
- **Controller**: `ShortenController::create()`

### 2. Redirect to Original URL
- **Method**: GET
- **Endpoint**: `/{shortCode}`
- **Response**: HTTP 302 redirect to original URL
- **Controller**: `RedirectController::redirect()`

## Database Schema

### urls Table
```sql
CREATE TABLE urls (
    id SERIAL PRIMARY KEY,
    original_url VARCHAR(2048) NOT NULL,
    short_code VARCHAR(8) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Implementation Details

### Short Code Generation
- **Length**: 8 characters
- **Character set**: Alphanumeric (a-z, A-Z, 0-9)
- **Uniqueness**: Generate new codes for duplicate URLs
- **Collision handling**: Regenerate if code already exists

### Error Handling
- **Invalid URLs**: Return 404 error
- **Malformed requests**: Return 404 error
- **Non-existent short codes**: Return 404 error
- **Database errors**: Return 404 error

### URL Validation
- **Protocol**: Accept only HTTP and HTTPS
- **Format**: Basic URL format validation
- **Length**: Maximum 2048 characters

## Docker Configuration

### Services
1. **Web Server**: Apache + PHP container
2. **Database**: PostgreSQL container
3. **Network**: Internal Docker network for service communication

### Containers
- Separate containers for better isolation and scalability
- Environment variables for database configuration
- Volume mounting for persistent data

## Development Approach

### Phase 1: Infrastructure Setup
- Docker configuration
- Database setup and migrations
- Apache configuration
- Basic project structure

### Phase 2: Core Implementation
- Database service implementation
- URL model with CRUD operations
- Utility functions (validation, code generation)

### Phase 3: API Implementation
- Main router setup
- Controllers implementation
- Error handling
- Request/response formatting

### Phase 4: Testing & Deployment
- Manual testing of all endpoints
- Docker compose setup
- Documentation updates

## Future Considerations (Post-MVP)

### Potential Edge Cases to Address Later
- Short code collisions and retry logic
- Database connection failure handling
- URL validation edge cases (localhost, special characters)
- Request validation (content-type, size limits)
- Performance optimizations
- Security enhancements

### Possible Enhancements
- User authentication and URL management
- Analytics and click tracking
- Custom short URLs
- URL expiration
- Rate limiting and spam protection
- Content filtering
- Batch URL shortening
- Frontend interface

## Getting Started

1. Set up Docker environment
2. Create project directory structure
3. Implement database service and models
4. Build controllers and routing
5. Configure Apache and Docker containers
6. Test API endpoints
7. Deploy using Docker Compose

---

*This document serves as the blueprint for the URL shortener MVP development.*
