<?php
/**
 * Book Management Page (Admin)
 * URL: /admin/books.php
 * SECURITY FIX: Added CSRF token validation and input sanitization
 */

require_once '../config.php';

if (!isAdmin()) {
    redirectToLogin();
}

$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle Add Book (FIXED: CSRF validation #5, Input sanitization #6)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    // Validate CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !validateCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);

        if (empty($title) || empty($author) || empty($genre) || $quantity <= 0) {
            $message = 'All fields are required and quantity must be positive';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO books (title, author, genre, quantity) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $title, $author, $genre, $quantity);

            if ($stmt->execute()) {
                $message = 'Book added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error adding book: ' . $conn->error;
                $message_type = 'danger';
            }
        }
    }
}

// Handle Update Book (FIXED: CSRF validation #5, Input sanitization #6)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_book'])) {
    // Validate CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !validateCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'danger';
    } else {
        $book_id = intval($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);

        if ($book_id <= 0 || empty($title) || empty($author) || empty($genre)) {
            $message = 'All fields are required';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, genre = ?, quantity = ? WHERE book_id = ?");
            $stmt->bind_param("sssii", $title, $author, $genre, $quantity, $book_id);

            if ($stmt->execute()) {
                $message = 'Book updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating book: ' . $conn->error;
                $message_type = 'danger';
            }
        }
    }
}

// Handle Delete Book (FIXED: CSRF validation #5)
if (isset($_GET['delete']) && isset($_GET[CSRF_TOKEN_NAME])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET[CSRF_TOKEN_NAME])) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'danger';
    } else {
        $book_id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);

        if ($stmt->execute()) {
            $message = 'Book deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting book: ' . $conn->error;
            $message_type = 'danger';
        }
    }
}

// Get all books
$books_result = $conn->query("SELECT * FROM books ORDER BY created_at DESC");
$csrf_token = getCSRFToken();

?>

<?php require_once '../includes/header.php'; ?>
<?php require_once '../includes/navbar.php'; ?>

<div class="container-main">
    <?php require_once '../includes/sidebar.php'; ?>
    
    <div class="main-content p-4">
        <!-- Page Header -->
        <div class="mb-5">
            <h1 class="text-pink fw-bold">
                <i class="fas fa-book me-2"></i>Manage Books
            </h1>
            <p class="text-muted">Add, edit, or delete books from your library</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Book Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>Add New Book
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- CSRF Token (FIXED: Issue #5) -->
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="col-md-6">
                        <label for="title" class="form-label">Book Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="col-md-6">
                        <label for="author" class="form-label">Author</label>
                        <input type="text" class="form-control" id="author" name="author" required>
                    </div>

                    <div class="col-md-6">
                        <label for="genre" class="form-label">Genre</label>
                        <select class="form-select" id="genre" name="genre" required>
                            <option value="">Select a genre</option>
                            <option value="Fiction">Fiction</option>
                            <option value="Non-Fiction">Non-Fiction</option>
                            <option value="Science">Science</option>
                            <option value="History">History</option>
                            <option value="Romance">Romance</option>
                            <option value="Mystery">Mystery</option>
                            <option value="Fantasy">Fantasy</option>
                            <option value="Biography">Biography</option>
                            <option value="Self-Help">Self-Help</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                    </div>

                    <div class="col-12">
                        <button type="submit" name="add_book" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Book
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Books Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Library Books (<?php echo $books_result->num_rows; ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="booksTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Genre</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($book = $books_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['genre']); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo $book['quantity']; ?> copies
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($book['quantity'] > 0): ?>
                                            <span class="badge badge-available">Available</span>
                                        <?php else: ?>
                                            <span class="badge badge-issued">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-mdb-toggle="modal" data-mdb-target="#editBookModal" onclick="loadBookData(<?php echo $book['book_id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>', '<?php echo htmlspecialchars($book['author']); ?>', '<?php echo htmlspecialchars($book['genre']); ?>', <?php echo $book['quantity']; ?>)">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <a href="?delete=<?php echo $book['book_id']; ?>&<?php echo CSRF_TOKEN_NAME; ?>=<?php echo htmlspecialchars($csrf_token); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this book?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Book Modal -->
<div class="modal fade" id="editBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Book</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST">
                <!-- CSRF Token (FIXED: Issue #5) -->
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="modal-body">
                    <input type="hidden" id="editBookId" name="book_id">

                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Book Title</label>
                        <input type="text" class="form-control" id="editTitle" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="editAuthor" class="form-label">Author</label>
                        <input type="text" class="form-control" id="editAuthor" name="author" required>
                    </div>

                    <div class="mb-3">
                        <label for="editGenre" class="form-label">Genre</label>
                        <select class="form-select" id="editGenre" name="genre" required>
                            <option value="">Select a genre</option>
                            <option value="Fiction">Fiction</option>
                            <option value="Non-Fiction">Non-Fiction</option>
                            <option value="Science">Science</option>
                            <option value="History">History</option>
                            <option value="Romance">Romance</option>
                            <option value="Mystery">Mystery</option>
                            <option value="Fantasy">Fantasy</option>
                            <option value="Biography">Biography</option>
                            <option value="Self-Help">Self-Help</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editQuantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="editQuantity" name="quantity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_book" class="btn btn-primary">Update Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadBookData(id, title, author, genre, quantity) {
    document.getElementById('editBookId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editAuthor').value = author;
    document.getElementById('editGenre').value = genre;
    document.getElementById('editQuantity').value = quantity;
}
</script>

<?php require_once '../includes/footer.php'; ?>
