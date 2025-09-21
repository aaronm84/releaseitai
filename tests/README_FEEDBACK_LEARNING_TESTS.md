# Feedback and Learning System - Test Suite Documentation

This document outlines the comprehensive test-driven development (TDD) test suite for the feedback and learning system based on the PRD requirements.

## Overview

The test suite defines the expected behavior for a complete feedback and learning system that captures user interactions, stores them with embeddings for similarity search, and uses retrieval-augmented generation (RAG) to improve AI responses over time.

## Test Structure

### 1. Model Tests (`tests/Unit/Models/`)

#### InputTest.php
Defines the expected behavior for the Input model that stores raw content from various sources:

**Key Test Categories:**
- Input creation with required fields (content, type, source)
- Content type validation (brain_dump, email, document, task_description)
- Source tracking (manual_entry, email_import, file_upload, api)
- Large content handling
- Metadata storage as JSON
- Relationships with Outputs and Embeddings
- Content preprocessing and hash generation
- Query scopes for filtering

**Expected Schema:**
```sql
inputs:
- id (bigint, primary key)
- content (text, required)
- type (string, required, indexed)
- source (string, required, indexed)
- metadata (json, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

#### OutputTest.php
Defines the expected behavior for the Output model that stores AI-generated results:

**Key Test Categories:**
- Output creation with AI model tracking
- Type validation (checklist, summary, action_items, stakeholder_list, communication)
- AI model tracking (claude-3-5-sonnet, gpt-4, etc.)
- Processing metadata (tokens, time, confidence)
- Relationships with Input, Feedback, and Embeddings
- Versioning for iterative improvements
- Quality scoring and metrics
- Feedback integration status
- Structured content formats

**Expected Schema:**
```sql
outputs:
- id (bigint, primary key)
- input_id (bigint, foreign key, indexed)
- content (text, required)
- type (string, required, indexed)
- ai_model (string, required, indexed)
- quality_score (decimal, nullable, indexed)
- version (integer, default 1)
- parent_output_id (bigint, foreign key, nullable)
- feedback_integrated (boolean, default false)
- feedback_count (integer, default 0)
- content_format (string, default 'text')
- metadata (json, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

#### FeedbackTest.php
Defines the expected behavior for the Feedback model that captures user interactions:

**Key Test Categories:**
- Inline feedback controls (accept ✅, edit ✏️, reject 🗑)
- Edit content tracking with corrections
- Passive signal tracking (task_completed, task_deleted, time_spent)
- User association for personalization
- Confidence scoring (0.0 to 1.0)
- Contextual metadata storage
- Temporal analysis capabilities
- Aggregation for learning patterns
- Signal type validation (explicit, passive)

**Expected Schema:**
```sql
feedback:
- id (bigint, primary key)
- output_id (bigint, foreign key, indexed)
- user_id (bigint, foreign key, indexed)
- type (string, required, indexed) // inline, behavioral
- action (string, required, indexed) // accept, edit, reject, task_completed, etc.
- signal_type (string, required, indexed) // explicit, passive
- confidence (decimal, required, 0.0-1.0, indexed)
- metadata (json, nullable)
- created_at (timestamp, indexed)
- updated_at (timestamp)
```

#### EmbeddingTest.php
Defines the expected behavior for the Embedding model with pgvector integration:

**Key Test Categories:**
- Vector storage in pgvector format
- Polymorphic relationships (Input/Output content)
- Vector validation and normalization
- Similarity search capabilities
- Batch operations for efficiency
- Model tracking (text-embedding-ada-002, etc.)
- Metadata for generation details
- Distance calculations
- Query scopes for filtering

**Expected Schema:**
```sql
embeddings:
- id (bigint, primary key)
- content_id (bigint, indexed)
- content_type (string, indexed)
- vector (vector/text, required) // pgvector type in production
- model (string, required, indexed)
- dimensions (integer, required, indexed)
- normalized (boolean, default false)
- metadata (json, nullable)
- created_at (timestamp, indexed)
- updated_at (timestamp)

UNIQUE INDEX: (content_id, content_type)
```

### 2. Service Tests (`tests/Unit/Services/`)

#### FeedbackServiceTest.php
Defines the expected behavior for the FeedbackService that captures and processes feedback:

**Key Test Categories:**
- Inline feedback capture (accept, edit, reject)
- Passive signal tracking (task completion, deletion, time spent)
- Feedback validation and error handling
- Learning pipeline processing
- Pattern aggregation and analysis
- Concurrent submission handling
- Confidence score calculation
- User preference learning

**Expected Methods:**
- `captureInlineFeedback(array $data): Feedback`
- `capturePassiveSignal(array $data): Feedback`
- `processFeedbackForLearning(array $data): array`
- `aggregateFeedbackPatterns(int $outputId): array`
- `calculateConfidenceScore(array $context): float`
- `updateUserPreferences(int $userId, array $history): array`

#### RetrievalServiceTest.php
Defines the expected behavior for the RetrievalService that provides RAG functionality:

**Key Test Categories:**
- Similarity search with pgvector
- Quality filtering of examples
- Context-based filtering
- User personalization
- RAG prompt building
- Caching for performance
- Similarity thresholds
- Edge case handling
- Batch retrieval operations

**Expected Methods:**
- `findSimilarFeedbackExamples(int $inputId, array $filters = []): Collection`
- `findPersonalizedExamples(int $inputId, int $userId, array $options = []): Collection`
- `buildRagPrompt(string $input, Collection $examples, array $config = []): string`

### 3. Integration Tests (`tests/Feature/`)

#### FeedbackLearningWorkflowTest.php
Defines the expected behavior for the complete end-to-end workflow:

**Key Test Scenarios:**
- Complete feedback learning cycle
- Multi-user personalization
- Large scale operations
- Error handling and recovery
- Performance under load
- Data consistency
- Real-world user scenarios

### 4. Database Schema Tests (`tests/Unit/Database/`)

#### FeedbackLearningSchemaTest.php
Defines the expected database schema and constraints:

**Key Test Categories:**
- Table structure validation
- Index optimization
- Foreign key constraints
- Data type support
- pgvector operations
- Performance indexes
- Analytical query support
- Data integrity rules

## Required Database Extensions

### PostgreSQL with pgvector
```sql
-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Example vector operations tested:
SELECT '[1,2,3]'::vector <-> '[4,5,6]'::vector; -- L2 distance
SELECT '[1,2,3]'::vector <=> '[4,5,6]'::vector; -- Cosine distance
SELECT '[1,2,3]'::vector <#> '[4,5,6]'::vector; -- Inner product
```

## Key Features Defined by Tests

### 1. Inline Feedback Controls
- ✅ Accept: User approves AI output (confidence: 1.0)
- ✏️ Edit: User corrects AI output (confidence: 0.7, stores corrections)
- 🗑 Reject: User rejects AI output (confidence: 1.0, stores reason)

### 2. Passive Signal Tracking
- Task completion from AI-generated checklists (confidence: 0.9)
- Task deletion indicating poor quality (confidence: 0.8)
- Time spent analyzing output (variable confidence based on engagement)

### 3. Retrieval-Augmented Generation (RAG)
- Similarity search using pgvector embeddings
- Quality filtering (min quality score, positive feedback only)
- Context filtering (release planning, bug fixing, etc.)
- User personalization based on feedback history
- Prompt building with relevant examples and corrections

### 4. Learning Pipeline
- Feedback pattern aggregation
- User preference learning
- Confidence scoring based on context
- Temporal analysis of feedback trends
- ML training data preparation

## Running the Tests

```bash
# Run all feedback learning tests
php artisan test tests/Unit/Models/InputTest.php
php artisan test tests/Unit/Models/OutputTest.php
php artisan test tests/Unit/Models/FeedbackTest.php
php artisan test tests/Unit/Models/EmbeddingTest.php
php artisan test tests/Unit/Services/FeedbackServiceTest.php
php artisan test tests/Unit/Services/RetrievalServiceTest.php
php artisan test tests/Feature/FeedbackLearningWorkflowTest.php
php artisan test tests/Unit/Database/FeedbackLearningSchemaTest.php

# Run specific test categories
php artisan test --filter=FeedbackLearning
```

## Implementation Guidelines

These tests define the contract for implementing the feedback and learning system. When implementing:

1. **Start with Models**: Implement the Eloquent models according to the test specifications
2. **Database Migrations**: Create migrations that pass the schema tests
3. **Service Layer**: Implement services following the method signatures defined in tests
4. **pgvector Integration**: Ensure pgvector extension is installed and configured
5. **Error Handling**: Implement robust error handling as defined in edge case tests
6. **Performance**: Optimize for the query patterns tested in the database tests

## Expected Benefits

This test-driven approach ensures:
- **Clear Specifications**: Every feature is precisely defined before implementation
- **Quality Assurance**: Tests validate both happy path and edge cases
- **Performance**: Database schema is optimized for common query patterns
- **Maintainability**: Changes are validated against comprehensive test suite
- **Documentation**: Tests serve as living documentation of system behavior

The comprehensive test suite provides a solid foundation for implementing a robust feedback and learning system that will continuously improve AI output quality through user interactions and retrieval-augmented generation.