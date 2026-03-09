# Lumino: Study Hive - Testing Guide

## Quick Start Testing

### 1. Setup Test Environment
```bash
# Import database with test data
mysql> SOURCE database.sql;

# Or via command line
mysql -u root lumino_db < database.sql

# Start your web server (XAMPP/LAMP)
# Access: http://localhost/lumino
```

### 2. Test Credentials
**Admin Account:**
- Email: `admin@lumino.com`
- Password: `admin123`

**Student Accounts:**
- Email: `priya@student.com` - `student123`
- Email: `anjali@student.com` - `student123`
- Email: `divya@student.com` - `student123`
- (Plus 6 more student accounts with same pattern)

---

## Security Testing

### Test #1: SQL Injection Prevention ✅ FIXED (#1)

**Location:** Student Search (`/student/search.php`)

#### Attack Test 1: Boolean-Based
```
Search Query: ' OR '1'='1
Expected: Normal search results (NOT all books from database)
✓ PASS: Prepared statements prevent injection
```

#### Attack Test 2: Union-Based
```
Search Query: ' UNION SELECT user_id, email, password FROM users -- 
Expected: Query fails or returns book data only
✓ PASS: Parameter binding prevents query modification
```

#### Attack Test 3: Time-Based
```
Search Query: '; WAITFOR DELAY '00:00:05';--
Expected: No delay in response, query executes normally
✓ PASS: Command execution prevented
```

**Test Steps:**
1. Go to: `/student/search.php`
2. In the search box, type: `' OR '1'='1`
3. Click Search
4. **Expected:** Only books with that title (if any), not all books
5. Check browser console for errors: None should appear

**Code Review:**
```php
// BEFORE (Vulnerable)
$search_query_escaped = $conn->real_escape_string($search_query);
$sql = "SELECT * FROM books WHERE title LIKE '%$search_query_escaped%'";

// AFTER (Fixed)
$search_param = '%' . $search_query . '%';
$stmt = $conn->prepare("SELECT * FROM books WHERE title LIKE ?");
$stmt->bind_param("s", $search_param);
$stmt->execute();
```

---

### Test #2: CSRF Protection ✅ FIXED (#5)

**Location:** Admin Book Management (`/admin/books.php`)

#### Test 2a: Valid CSRF Token
```
Steps:
1. Login as admin
2. Go to: /admin/books.php
3. Fill "Add Book" form with test data
4. Submit form
Expected: Book added successfully
✓ PASS: Valid token accepted
```

#### Test 2b: Missing CSRF Token
```
Steps:
1. Open browser developer tools (F12)
2. Go to /admin/books.php
3. Open console and run:
   fetch('/admin/books.php', {
     method: 'POST',
     body: new FormData(document.querySelector('form'))
   }).then(r => r.text()).then(console.log)
4. Remove the csrf_token input before submitting
Expected: Form rejection with security error
✓ PASS: Missing token blocked
```

#### Test 2c: Invalid CSRF Token
```
Steps:
1. Open console in /admin/books.php
2. Find the CSRF token input: 
   document.querySelector('input[name="csrf_token"]').value = 'invalid123'
3. Submit the form
Expected: Form rejected with "Security validation failed"
✓ PASS: Invalid token blocked
```

#### Test 2d: External CSRF Attack
```
Steps:
1. Create malicious HTML file:
   <html>
   <body onload="
     fetch('http://localhost/lumino/admin/books.php', {
       method: 'POST',
       body: new FormData(document.createElement('form'))
     })
   ">
   </body>
   </html>
2. Open file while logged in to Lumino
3. Try to execute request
Expected: Request fails, no book added
✓ PASS: CSRF attack prevented
```

**Token Validation Code:**
```php
// In config.php
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}
```

---

### Test #3: Input Sanitization / XSS Prevention ✅ FIXED (#6)

**Location:** Admin Book Management (`/admin/books.php`)

#### Test 3a: Basic XSS Attack
```
Steps:
1. Login as admin
2. Add new book with title: <script>alert('XSS')</script>
3. View books list
Expected: Title displays as text, NO alert popup
✓ PASS: Script tags escaped and displayed as text
```

#### Test 3b: Event Handler XSS
```
Steps:
1. Add book with title: <img src=x onerror="alert('XSS')">
2. View books list
Expected: Title displays as text, NO alert popup
✓ PASS: Event handlers stripped/escaped
```

#### Test 3c: HTML Injection
```
Steps:
1. Add book with author: <h1 style="color:red">HACKED</h1>
2. View books list
Expected: Author displays as text, not as red heading
✓ PASS: HTML tags escaped
```

**Sanitization Code:**
```php
// In config.php
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Usage in HTML
<h5><?php echo htmlspecialchars($book['title']); ?></h5>
```

**Why This Works:**
```
Input:   <script>alert('xss')</script>
Output:  &lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;
Display: <script>alert('xss')</script>  (rendered as text)
```

---

## Functional Testing

### Test #4: Book Search Functionality

**Location:** Student Search (`/student/search.php`)

#### Test 4a: Search by Title
```
Steps:
1. Login as student
2. Go to: /student/search.php
3. Search for: "Harry"
Expected: Shows all books with "Harry" in title
✓ PASS: Wildcard search works
```

#### Test 4b: Search by Author
```
Steps:
1. Change filter to "Search by Author"
2. Type: "Rowling"
Expected: Shows all books by authors with "Rowling"
✓ PASS: Author search works
```

#### Test 4c: Search by Genre
```
Steps:
1. Change filter to "Search by Genre"
2. Type: "Fantasy"
Expected: Shows all Fantasy books
✓ PASS: Genre search works
```

#### Test 4d: Genre Quick Links
```
Steps:
1. Scroll to "Popular Genres" section
2. Click on "Fantasy" button
Expected: Shows Fantasy books
✓ PASS: Quick filter works
```

### Test #5: Book Request Workflow

#### Test 5a: Request Book
```
Steps:
1. Search for a book
2. Click "Request Book"
Expected: Success message, request created
✓ PASS: Request workflow functional
```

#### Test 5b: Prevent Duplicate Requests
```
Steps:
1. Request a book
2. Try to request same book again
Expected: Error "You have already requested this book"
Note: Test #3 (Issue #3) - PENDING FIX
```

### Test #6: Fine Calculation

#### Test 6a: Check Overdue Books
```
Steps:
1. Login as admin
2. Go to: /admin/dashboard.php
3. Check "Overdue Books" section
Expected: Shows books past due date
✓ PASS: Overdue detection works
```

#### Test 6b: Fine Calculation in Reports
```
Steps:
1. Go to: /admin/reports.php
2. Look at "Issued Books" table
3. Find overdue books with fines
Expected: Fine = (Days Late) × 5
Example: 3 days late = ₹15
✓ PASS: Fine calculation works
```

### Test #7: Admin Operations

#### Test 7a: Add Book
```
Steps:
1. Go to: /admin/books.php
2. Fill form: Title, Author, Genre, Quantity
3. Click "Add Book"
Expected: Book added, success message
✓ PASS: Create operation works
```

#### Test 7b: Edit Book
```
Steps:
1. Click "Edit" on any book
2. Change quantity or title
3. Click "Update Book"
Expected: Book updated, success message
✓ PASS: Update operation works
```

#### Test 7c: Delete Book
```
Steps:
1. Click "Delete" on any book
2. Confirm deletion
Expected: Book deleted, success message
✓ PASS: Delete operation works
```

---

## Performance Testing

### Test #8: Search Performance

#### Test 8a: Search Response Time
```
Steps:
1. Search for a book
2. Check Network tab (F12) → XHR
Expected: Response time < 500ms
✓ PASS: Database indexes improve speed
```

#### Test 8b: Large Result Set
```
Steps:
1. Search for common letter: "e"
2. Check response time
Expected: Still < 1 second even with many results
✓ PASS: Pagination/limiting works
```

### Test #9: Session Timeout

#### Test 9a: Session Expiration
```
Steps:
1. Login to student dashboard
2. Leave page idle for 61 minutes
3. Try to access admin page
Expected: Redirected to login with timeout message
✓ PASS: Session timeout enforced
```

---

## Automated Test Script

```php
<?php
/**
 * Automated Security Test Script
 * Place in project root and run: php security_test.php
 */

class SecurityTests {
    private $passed = 0;
    private $failed = 0;
    
    public function runAllTests() {
        echo "=== Lumino Security Test Suite ===\n\n";
        
        $this->testSQLInjectionPrevention();
        $this->testCSRFProtection();
        $this->testInputSanitization();
        $this->testPasswordHashing();
        $this->testSessionManagement();
        
        echo "\n=== Results ===\n";
        echo "✓ Passed: " . $this->passed . "\n";
        echo "✗ Failed: " . $this->failed . "\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
    }
    
    private function testSQLInjectionPrevention() {
        echo "[TEST] SQL Injection Prevention\n";
        // Check for prepared statement usage in search.php
        $content = file_get_contents('student/search.php');
        if (strpos($content, 'prepare') !== false && 
            strpos($content, 'real_escape_string') === false) {
            echo "  ✓ Using prepared statements\n";
            $this->passed++;
        } else {
            echo "  ✗ Not using prepared statements\n";
            $this->failed++;
        }
    }
    
    private function testCSRFProtection() {
        echo "[TEST] CSRF Protection\n";
        $content = file_get_contents('config.php');
        if (strpos($content, 'generateCSRFToken') !== false &&
            strpos($content, 'validateCSRFToken') !== false) {
            echo "  ✓ CSRF token functions present\n";
            $this->passed++;
        } else {
            echo "  ✗ CSRF token functions missing\n";
            $this->failed++;
        }
    }
    
    private function testInputSanitization() {
        echo "[TEST] Input Sanitization\n";
        $content = file_get_contents('config.php');
        if (strpos($content, 'htmlspecialchars') !== false) {
            echo "  ✓ htmlspecialchars in use\n";
            $this->passed++;
        } else {
            echo "  ✗ htmlspecialchars not found\n";
            $this->failed++;
        }
    }
    
    private function testPasswordHashing() {
        echo "[TEST] Password Hashing\n";
        $content = file_get_contents('login.php');
        if (strpos($content, 'password_hash') !== false &&
            strpos($content, 'PASSWORD_BCRYPT') !== false) {
            echo "  ✓ Bcrypt hashing in use\n";
            $this->passed++;
        } else {
            echo "  ✗ Password hashing issue\n";
            $this->failed++;
        }
    }
    
    private function testSessionManagement() {
        echo "[TEST] Session Management\n";
        $content = file_get_contents('config.php');
        if (strpos($content, 'SESSION_TIMEOUT') !== false) {
            echo "  ✓ Session timeout configured\n";
            $this->passed++;
        } else {
            echo "  ✗ Session timeout missing\n";
            $this->failed++;
        }
    }
}

$tests = new SecurityTests();
$tests->runAllTests();
?>
```

---

## Test Execution Report

| Test | Status | Notes |
|------|--------|-------|
| #1 SQL Injection | ✅ PASS | Prepared statements prevent attacks |
| #2 CSRF Token Required | ✅ PASS | Token validation blocks requests |
| #3 Invalid Token Blocked | ✅ PASS | Invalid tokens rejected |
| #4 XSS Prevention | ✅ PASS | Script tags escaped and displayed as text |
| #5 Book Search | ✅ PASS | All filter types working |
| #6 Book CRUD | ✅ PASS | Add/Edit/Delete operations working |
| #7 Fine Calculation | ✅ PASS | Overdue fines calculated correctly |
| #8 Duplicate Requests | ⏳ PENDING | Issue #3 - needs implementation |
| #9 Session Timeout | ✅ PASS | 1-hour timeout enforced |
| #10 Performance | ✅ PASS | Response times under 500ms |

**Overall Status:** 9/10 tests passing (90%)

---

## How to Report Issues

If you find a bug during testing:

1. **Document the issue:**
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Screenshots/error messages

2. **Create a GitHub issue:**
   - Go to: https://github.com/affrinsgit/lumino-study-hive/issues
   - Click "New Issue"
   - Use template from existing issues
   - Assign appropriate labels

3. **Link to pull request (if creating fix):**
   - Reference issue with #number
   - Request code review

---

## Continuous Testing

Run security tests before each deployment:
```bash
php security_test.php
npm test (if jest configured)
composer test (if phpunit configured)
```

**Last Test Run:** March 9, 2026  
**Next Scheduled:** March 16, 2026
