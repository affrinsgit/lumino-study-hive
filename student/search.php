<?php
/**
 * Book Search & Request Page (Student)
 * URL: /student/search.php
 * SECURITY FIX: Replaced real_escape_string() with prepared statements
 */

require_once '../config.php';

if (!isStudent()) {
    redirectToLogin();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$search_query = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'title';

$books = [];

// Build search query using prepared statements (FIXED: SQL Injection vulnerability)
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    
    if ($filter === 'title') {
        $stmt = $conn->prepare("SELECT * FROM books WHERE title LIKE ? ORDER BY title ASC");
        $stmt->bind_param("s", $search_param);
    } elseif ($filter === 'author') {
        $stmt = $conn->prepare("SELECT * FROM books WHERE author LIKE ? ORDER BY author ASC");
        $stmt->bind_param("s", $search_param);
    } elseif ($filter === 'genre') {
        $stmt = $conn->prepare("SELECT * FROM books WHERE genre LIKE ? ORDER BY genre ASC");
        $stmt->bind_param("s", $search_param);
    } else {
        $stmt = $conn->prepare("SELECT * FROM books ORDER BY title ASC LIMIT 20");
    }
    
    if ($stmt) {
        $stmt->execute();
        $books_result = $stmt->get_result();
        while ($book = $books_result->fetch_assoc()) {
            $books[] = $book;
        }
        $stmt->close();
    }
} else {
    // Show recent books if no search
    $stmt = $conn->prepare("SELECT * FROM books ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $books_result = $stmt->get_result();
    while ($book = $books_result->fetch_assoc()) {
        $books[] = $book;
    }
    $stmt->close();
}

// Get genres for filter
$stmt = $conn->prepare("SELECT DISTINCT genre FROM books ORDER BY genre ASC");
$stmt->execute();
$genres_result = $stmt->get_result();
$genres = [];
while ($genre = $genres_result->fetch_assoc()) {
    $genres[] = $genre['genre'];
}
$stmt->close();

$conn->close();

?>

<?php require_once '../includes/header.php'; ?>
<?php require_once '../includes/navbar.php'; ?>

<div class="container-main">
    <?php require_once '../includes/sidebar.php'; ?>
    
    <div class="main-content p-4">
        <!-- Page Header -->
        <div class="mb-5">
            <h1 class="text-pink fw-bold">
                <i class="fas fa-search me-2"></i>Search Books
            </h1>
            <p class="text-muted">Browse and request books from our library</p>
        </div>

        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-7">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search for books..." 
                                   name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-filter"></i>
                            </span>
                            <select class="form-select" name="filter" onchange="this.form.submit()">
                                <option value="title" <?php echo $filter === 'title' ? 'selected' : ''; ?>>
                                    Search by Title
                                </option>
                                <option value="author" <?php echo $filter === 'author' ? 'selected' : ''; ?>>
                                    Search by Author
                                </option>
                                <option value="genre" <?php echo $filter === 'genre' ? 'selected' : ''; ?>>
                                    Search by Genre
                                </option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Genre Quick Links -->
        <div class="mb-4">
            <h6 class="text-muted mb-3">Popular Genres:</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($genres as $genre): ?>
                    <a href="?q=<?php echo urlencode($genre); ?>&filter=genre" 
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($genre); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Books Grid -->
        <?php if (!empty($books)): ?>
            <div class="row">
                <?php foreach ($books as $book): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div style="height: 150px; background: linear-gradient(135deg, #E6E6FA 0%, #FFDAB9 100%); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                                    <i class="fas fa-book" style="font-size: 3rem; color: var(--primary-pink); opacity: 0.5;"></i>
                                </div>

                                <h5 class="card-title" style="min-height: 3rem;">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h5>

                                <p class="text-muted mb-2">
                                    <i class="fas fa-pen me-1"></i>
                                    <?php echo htmlspecialchars($book['author']); ?>
                                </p>

                                <p class="mb-2">
                                    <small class="badge badge-primary">
                                        <?php echo htmlspecialchars($book['genre']); ?>
                                    </small>
                                </p>

                                <p class="mb-3">
                                    <?php if ($book['quantity'] > 0): ?>
                                        <span class="badge badge-available">
                                            <?php echo $book['quantity']; ?> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-issued">
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </p>

                                <?php if ($book['quantity'] > 0): ?>
                                    <button type="button" class="btn btn-pink w-100" 
                                            onclick="requestBook(<?php echo $book['book_id']; ?>)">
                                        <i class="fas fa-hand-paper me-2"></i>Request Book
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-times me-2"></i>Not Available
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-inbox" style="font-size: 2rem;"></i>
                <p class="mt-3 mb-0">No books found. Try searching with different keywords.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
