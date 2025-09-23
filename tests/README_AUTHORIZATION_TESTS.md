# Authorization Test Suite Documentation

This document provides comprehensive documentation for the authorization test suite designed for the ReleaseIt Laravel application. These tests follow Test-Driven Development (TDD) principles to define expected authorization behavior before implementation.

## Overview

The authorization test suite covers all critical security aspects of the application, ensuring that users can only access resources they're authorized to view or modify. The tests are organized into several categories, each focusing on specific authorization patterns.

## Test Structure

### 1. Policy Unit Tests (`tests/Unit/Policies/`)

These tests define the core authorization logic for each resource type using Laravel's Policy system.

#### Files:
- `WorkstreamPolicyTest.php` - Tests for workstream ownership and permission inheritance
- `ReleasePolicyTest.php` - Tests for release access based on workstream permissions and stakeholder roles
- `FeedbackPolicyTest.php` - Tests for user-owned feedback privacy and access control
- `ContentPolicyTest.php` - Tests for content visibility based on associations and permissions
- `UserPolicyTest.php` - Tests for user profile access and privacy controls

#### Key Test Patterns:
- **Resource Ownership**: Users can only access/modify their own resources
- **Permission Delegation**: Authorized users can grant permissions to others
- **Inherited Permissions**: Workstream permissions cascade to child workstreams
- **Role-Based Access**: Different permissions for different stakeholder roles
- **Privacy Controls**: Personal data protection and user isolation

### 2. Authorization Integration Tests (`tests/Feature/Authorization/`)

These tests verify that authorization policies are properly enforced at the API endpoint level.

#### Files:
- `WorkstreamAuthorizationTest.php` - API endpoint authorization for workstream operations
- `ReleaseAuthorizationTest.php` - API endpoint authorization for release operations
- `FeedbackAuthorizationTest.php` - API endpoint authorization for feedback operations

#### Key Test Scenarios:
- Unauthenticated access attempts
- CRUD operations with various permission levels
- Bulk operations respecting individual permissions
- Search operations filtering by access rights

### 3. Permission Inheritance Tests (`tests/Feature/Authorization/`)

#### File:
- `WorkstreamHierarchyPermissionTest.php` - Tests for hierarchical permission inheritance

#### Key Test Cases:
- Multi-level hierarchy permission cascading
- `workstream_and_children` vs `workstream_only` scope differences
- Direct permissions overriding inherited permissions
- Permission boundaries across hierarchy levels

### 4. Role-Based Permission Tests (`tests/Feature/Authorization/`)

#### File:
- `RoleBasedPermissionTest.php` - Tests for different user role permissions

#### User Roles Tested:
- **Regular User**: Basic access to own resources
- **Project Manager**: Enhanced project management capabilities
- **Product Manager**: Strategic oversight and workstream ownership
- **Stakeholder**: Role-specific release access (viewer/reviewer/approver)
- **Admin**: System-wide access with proper auditing
- **System User**: Data access for AI/learning purposes
- **Workstream Owner**: Full ownership rights and delegation

### 5. Security Boundary Tests (`tests/Feature/Authorization/`)

#### File:
- `SecurityBoundaryTest.php` - Tests for security vulnerabilities and attack prevention

#### Security Aspects Covered:
- **Cross-Tenant Data Isolation**: Prevention of data leakage between organizations
- **SQL Injection Prevention**: Input validation and parameterized queries
- **Mass Assignment Protection**: Prevention of unauthorized field modifications
- **Enumeration Attack Prevention**: Protection against resource ID guessing
- **Permission Escalation Prevention**: Blocking unauthorized permission grants
- **Error Message Security**: Preventing information disclosure through errors
- **Session Security**: Protection against session hijacking
- **Rate Limiting**: Brute force attack prevention

## Key Authorization Patterns

### 1. Resource Ownership
```php
// Test Pattern: Owner can access, others cannot
public function test_owner_can_view_their_own_resource()
{
    $result = $this->policy->view($this->owner, $this->resource);
    $this->assertTrue($result);
}

public function test_non_owner_cannot_view_resource()
{
    $result = $this->policy->view($this->otherUser, $this->resource);
    $this->assertFalse($result);
}
```

### 2. Permission Inheritance
```php
// Test Pattern: Parent permissions cascade to children
public function test_inherited_permission_allows_child_access()
{
    // Given: Permission on parent with 'workstream_and_children' scope
    WorkstreamPermission::create([
        'workstream_id' => $parent->id,
        'user_id' => $user->id,
        'scope' => 'workstream_and_children'
    ]);

    // Then: Can access child workstream
    $result = $this->policy->view($user, $childWorkstream);
    $this->assertTrue($result);
}
```

### 3. Role-Based Access
```php
// Test Pattern: Different roles have different capabilities
public function test_stakeholder_role_determines_access_level()
{
    // Given: User with 'viewer' role
    StakeholderRelease::create([
        'user_id' => $user->id,
        'release_id' => $release->id,
        'role' => 'viewer'
    ]);

    // Then: Can view but not update
    $this->assertTrue($this->policy->view($user, $release));
    $this->assertFalse($this->policy->update($user, $release));
}
```

### 4. Security Boundaries
```php
// Test Pattern: Cross-tenant access prevention
public function test_cross_tenant_access_is_prevented()
{
    // When: User from Org1 tries to access Org2 resource
    $response = $this->actingAs($org1User)
        ->getJson("/api/resources/{$org2Resource->id}");

    // Then: Access should be denied
    $response->assertStatus(403);
}
```

## Expected Authorization Behavior

### Workstreams
- **Owners**: Full CRUD access, permission management, ownership transfer
- **Edit Permission**: Create/update workstreams and releases, manage stakeholders
- **View Permission**: Read-only access to workstream and associated resources
- **Inheritance**: Permissions cascade to child workstreams when scope is `workstream_and_children`

### Releases
- **Workstream Permissions**: Inherited from parent workstream
- **Stakeholder Roles**:
  - `viewer`: Read-only access
  - `reviewer`: Read-only access with notification preferences
  - `approver`: Read and update access
  - `manager`: Full management except deletion
- **Owner/Editor**: Can manage stakeholders and delete releases

### Feedback
- **User Ownership**: Users can only access their own feedback
- **System Access**: System users can access aggregated data for learning
- **Privacy**: Individual feedback is never shared between users
- **Time Limits**: Feedback can only be edited within 24 hours

### Content
- **User Ownership**: Users can access their own content
- **Association Access**: Content associated with accessible workstreams/releases can be viewed
- **Processing Status**: Unprocessed content only visible to owner
- **Type Restrictions**: Sensitive content types have additional restrictions

### Users
- **Self Access**: Users can view and update their own profiles
- **Colleague Access**: Limited profile info for users working on same workstreams
- **Admin Access**: Full access with audit logging
- **Privacy**: Personal information protected from unauthorized access

## Running the Tests

### Prerequisites
```bash
# Ensure database is set up for testing
php artisan migrate --env=testing
```

### Running All Authorization Tests
```bash
# Run all policy unit tests
php artisan test tests/Unit/Policies

# Run all authorization feature tests
php artisan test tests/Feature/Authorization

# Run specific test file
php artisan test tests/Unit/Policies/WorkstreamPolicyTest

# Run with coverage
php artisan test --coverage
```

### Test Environment Setup
The tests use Laravel's RefreshDatabase trait to ensure clean state between tests. Each test creates the necessary users, resources, and permissions for its specific scenario.

## Implementation Guidelines

### Policy Classes to Implement
Based on these tests, you'll need to create the following Laravel Policy classes:

1. `app/Policies/WorkstreamPolicy.php`
2. `app/Policies/ReleasePolicy.php`
3. `app/Policies/FeedbackPolicy.php`
4. `app/Policies/ContentPolicy.php`
5. `app/Policies/UserPolicy.php`

### Controller Authorization
Each controller should use the `authorize()` method to enforce policies:

```php
public function show(Workstream $workstream)
{
    $this->authorize('view', $workstream);
    return new WorkstreamResource($workstream);
}

public function update(Request $request, Workstream $workstream)
{
    $this->authorize('update', $workstream);
    // Update logic...
}
```

### Middleware and Guards
- Use `auth:sanctum` middleware for API authentication
- Implement rate limiting for security endpoints
- Add custom middleware for tenant isolation if needed

## Test Data Factories

The tests rely on model factories for creating test data. Ensure the following factories exist:

- `UserFactory`
- `WorkstreamFactory`
- `ReleaseFactory`
- `FeedbackFactory`
- `ContentFactory`
- `WorkstreamPermissionFactory`
- `StakeholderReleaseFactory`
- `InputFactory`
- `OutputFactory`

## Security Considerations

### Critical Security Requirements
1. **No Cross-Tenant Data Access**: Users must never see data from other organizations
2. **Permission Validation**: Every resource access must be authorized
3. **Input Sanitization**: Prevent SQL injection and XSS attacks
4. **Error Message Security**: Don't leak sensitive information in error responses
5. **Audit Logging**: Track admin access and permission changes
6. **Session Security**: Implement proper session management
7. **Rate Limiting**: Prevent brute force attacks

### Compliance and Privacy
- Implement GDPR-compliant data access and deletion
- Ensure user feedback privacy is maintained
- Provide secure data export functionality
- Maintain audit trails for compliance

## Troubleshooting

### Common Test Failures
1. **Policy Not Found**: Ensure policy classes are created and registered
2. **Permission Denied**: Check that test data setup includes necessary permissions
3. **Factory Issues**: Verify all required factories exist and have proper relationships
4. **Database Constraints**: Ensure test database has proper foreign key constraints

### Debugging Authorization Issues
1. Use `dd()` in tests to inspect policy responses
2. Check `auth()->user()` in policy methods
3. Verify model relationships are properly loaded
4. Test with different user roles and permissions

## Next Steps

1. **Implement Policy Classes**: Create the actual policy classes based on test specifications
2. **Add Controller Authorization**: Implement authorization checks in controllers
3. **Security Hardening**: Add additional security middleware and validation
4. **Performance Testing**: Test authorization performance with large datasets
5. **Audit Implementation**: Add comprehensive audit logging
6. **Documentation**: Update API documentation with authorization requirements

This test suite provides a comprehensive foundation for implementing secure, role-based authorization in the ReleaseIt application. Follow TDD principles by ensuring all tests pass before considering the authorization system complete.