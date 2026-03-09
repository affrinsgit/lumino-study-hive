# Lumino: Study Hive - Bug Report & Testing Summary

## Overview
This document outlines identified bugs, their severity, and fixes applied to the Lumino: Study Hive Smart Library Management System.

## Date: March 9, 2026
**Total Issues Identified:** 6  
**High Priority:** 3  
**Medium Priority:** 3  

---

## Issues & Fixes

### 🔴 Issue #1: SQL Injection in Student Search (HIGH)
**Status:** ✅ FIXED  
**File:** `student/search.php`  
**PR:** [#7](https://github.com/affrinsgit/lumino-study-hive/pull/7)

**Problem:**
```php
// VULNERABLE CODE
$search_query_escaped = $conn->real_escape_string($search_query);
$sql = "SELECT * FROM books WHERE title LIKE '%$search_query_escaped%'";
$books_result = $conn->query($sql);
```

**Attack Vector:**
An attacker could bypass search by using: `' OR '1'='1` to return all books or access unauthorized data.

**Fix:**
Replaced with prepared statements:
```php
// SECURE CODE
$search_param = '%' . $search_query . '%';
$stmt = $conn->prepare("SELECT * FROM books WHERE title LIKE ? ORDER BY title ASC");
$stmt->bind_param("s", $search_param);
$stmt->execute();
$books_result = $stmt->get_result();
```

**Why This Works:**
- Separates query structure from data
- Database driver handles parameter escaping
- SQL injection attempts are treated as literal strings

---

### 🔴 Issue #5: No CSRF Protection (HIGH)
**Status:** ✅ FIXED  
**Files:** `config.php`, `admin/books.php`  
**PR:** [#8](https://github.com/affrinsgit/lumino-study-hive/pull/8)

**Problem:**
Forms lack CSRF tokens, allowing attackers to forge requests from external websites.

**Attack Scenario:**
1. Admin logs into Lumino
2. Admin visits malicious website
3. Malicious site sends: `<img src="http://lumino/admin/books.php?delete=1">`
4. Admin's browser automatically includes authentication cookies
5. Book is deleted without admin's knowledge

**Fix:**
Added CSRF token system in `config.php`:
```php
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

Updated `admin/books.php` forms:
```php
// In form
<input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

// On submission
if (!validateCSRFToken($_POST['csrf_token'])) {
    die('Security validation failed');
}
```

---

### 🔴 Issue #6: Missing Input Sanitization (HIGH)
**Status:** ✅ FIXED  
**Files:** `config.php`, `admin/books.php`  
**PR:** [#8](https://github.com/affrinsgit/lumino-study-hive/pull/8)

**Problem:**
User input displayed without HTML escaping, vulnerable to XSS attacks.

**Attack Scenario:**
1. Admin adds book with title: `<script>alert('Hacked!')</script>`
2. Book title stored in database
3. When book list displays, JavaScript executes in admin's browser
4. Attacker could steal session cookies or admin actions

**Fix:**
Added sanitization helper in `config.php`:
```php
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
```

All user data now escaped on display:
```php
// SECURE
<h5><?php echo htmlspecialchars($book['title']); ?></h5>

// Applied to all output
<?php echo htmlspecialchars($book['author']); ?>
<?php echo htmlspecialchars($book['genre']); ?>
```

**Defense Mechanism:**
- `htmlspecialchars()` converts special characters to HTML entities
- `<` becomes `&lt;` (rendered as text, not HTML tag)
- `ENT_QUOTES` escapes both double and single quotes
- JavaScript cannot execute when treated as text

---

### 🟡 Issue #2: Missing Database Connection Check in Logout (MEDIUM)
**Status:** ⏳ PENDING  
**File:** `logout.php`  
**Issue:** [#2](https://github.com/affrinsgit/lumino-study-hive/issues/2)

**Problem:**
Logout may not properly clear session data, potentially leaving sensitive information in memory.

**Recommended Fix:**
```php
<?php
require_once 'config.php';

// Proper session cleanup
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout event (optional)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Optional: Log logout event to database
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: ' . SITE_URL . '/login.php?logout=1');
exit;
?>
```

---

### 🟡 Issue #3: No Validation for Duplicate Book Requests (MEDIUM)
**Status:** ⏳ PENDING  
**File:** `api/request_book.php`  
**Issue:** [#3](https://github.com/affrinsgit/lumino-study-hive/issues/3)

**Problem:**
Students can submit multiple requests for the same book without validation.

**Scenario:**
1. Student requests "Harry Potter"
2. Clicks request again before page reloads
3. System allows duplicate request
4. Admin sees multiple identical requests
5. Database grows with unnecessary records

**Recommended Fix:**
```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $user_id = $_SESSION['user_id'];
    $book_id = intval($_POST['book_id']);
    
    $conn = getDBConnection();
    
    // Check for existing request
    $stmt = $conn->prepare(
        "SELECT request_id FROM book_requests 
         WHERE user_id = ? AND book_id = ? 
         AND status IN ('pending', 'approved')"
    );
    $stmt->bind_param("ii", $user_id, $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Duplicate request exists
        http_response_code(409); // Conflict
        echo json_encode([
            'status' => 'error',
            'message' => 'You have already requested this book'
        ]);
        exit;
    }
    
    // Proceed with request...
}
?>
```

---

### 🟡 Issue #4: Fine Calculation Not Accounting for Return Status (MEDIUM)
**Status:** ⏳ PENDING  
**File:** `config.php` (calculateFine function)  
**Issue:** [#4](https://github.com/affrinsgit/lumino-study-hive/issues/4)

**Problem:**
Fine calculation doesn't verify if book is still issued or already returned.

**Scenario:**
1. Book due on March 1, returned March 5 (overdue)
2. Fine calculated: ₹15
3. Six months later, system recalculates fine: still ₹15
4. If book returned late multiple times, fine could be recalculated

**Current Implementation:**
```php
function calculateFine($dueDate, $returnDate) {
    $due = strtotime($dueDate);
    $return = strtotime($returnDate);
    
    if ($return <= $due) {
        return 0;
    }
    
    $lateDays = floor(($return - $due) / (60 * 60 * 24));
    return $lateDays * FINE_PER_DAY;
}
```

**Problem:** Only checks date logic, not record status

**Recommended Fix:**
```php
function calculateFine($dueDate, $returnDate, $status = null) {
    // Only calculate fine for still-issued or overdue books
    if ($status && !in_array($status, ['issued', 'overdue'])) {
        return 0;
    }
    
    // If no return date, book hasn't been returned
    if (!$returnDate || $returnDate === null) {
        // Calculate fine based on today's date
        $due = strtotime($dueDate);
        $today = strtotime(date('Y-m-d'));
        
        if ($today <= $due) {
            return 0;
        }
        
        $lateDays = floor(($today - $due) / (60 * 60 * 24));
        return $lateDays * FINE_PER_DAY;
    }
    
    // Book has been returned
    $due = strtotime($dueDate);
    $return = strtotime($returnDate);
    
    if ($return <= $due) {
        return 0;
    }
    
    $lateDays = floor(($return - $due) / (60 * 60 * 24));
    return $lateDays * FINE_PER_DAY;
}
```

---

## Testing Checklist

### Security Tests
- [ ] **SQL Injection Test**
  - Search for: `' OR '1'='1`
  - Search for: `"; DROP TABLE books; --`
  - Expected: Normal search, no database changes

- [ ] **CSRF Test**
  - Remove CSRF token from form
  - Submit book form
  - Expected: Form rejected with error

- [ ] **XSS Test**
  - Add book with title: `<script>alert('xss')</script>`
  - View books list
  - Expected: Title displays as text, no alert

### Functionality Tests
- [ ] Book search works with special characters
- [ ] Admin can add/edit/delete books
- [ ] Logout clears session properly
- [ ] Duplicate requests are prevented
- [ ] Fines calculated correctly

---

## Summary

| Issue | Status | Priority | Type |
|-------|--------|----------|------|
| #1 SQL Injection | ✅ Fixed | High | Security |
| #2 Session Cleanup | ⏳ Pending | Medium | Security |
| #3 Duplicate Requests | ⏳ Pending | Medium | Logic |
| #4 Fine Calculation | ⏳ Pending | Medium | Logic |
| #5 CSRF Protection | ✅ Fixed | High | Security |
| #6 Input Sanitization | ✅ Fixed | High | Security |

**Progress:** 3/6 issues fixed (50%)  
**Security Status:** Critical vulnerabilities addressed

---

## Next Steps
1. Review and merge pull requests #7 and #8
2. Apply pending fixes for issues #2, #3, and #4
3. Run comprehensive test suite
4. Deploy to production with security updates
