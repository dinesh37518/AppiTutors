<?php
/**
 * Manager Blog & Editorial Management View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$statuses = ['ALL' => 'All Statuses', 'DRAFT' => 'Drafts', 'PUBLISHED' => 'Published', 'ARCHIVED' => 'Archived'];
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <a href="/manager-dashboard.php">Administration</a> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Blog &amp; Editorial</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Blog Editorial &amp; Publications</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Author, review, publish, and archive UK curriculum revision articles and educational insights.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'blog';
        include __DIR__ . '/manager-nav.php'; 
        ?>

        <!-- Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Success:</span>
                <span><?= $e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= $e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Post Authoring / Editing Card -->
        <div class="card" style="padding: 2rem; margin-bottom: 2.5rem; border-top: 4px solid var(--color-primary-600);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-size: 1.25rem; margin: 0;">
                        <?= $editPost ? 'Edit Blog Post #' . $e($editPost['id']) : 'Author New Blog Post' ?>
                    </h2>
                    <p style="color: var(--color-navy-500); font-size: 0.875rem; margin: 0;">
                        <?= $editPost ? 'Modify post content, metadata, or publication status.' : 'Compose an educational article for parents and students.' ?>
                    </p>
                </div>
                <?php if ($editPost): ?>
                    <a href="/manager-blog.php" class="btn btn-secondary btn-sm">&larr; Cancel Editing</a>
                <?php endif; ?>
            </div>

            <form action="/manager-blog.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $editPost ? 'UPDATE' : 'CREATE' ?>">
                <?php if ($editPost): ?>
                    <input type="hidden" name="post_id" value="<?= $e($editPost['id']) ?>">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin: 0;">
                        <label for="blog-title" class="form-label form-label-required">Post Title</label>
                        <input type="text" id="blog-title" name="title" class="form-input" required maxlength="255" 
                               placeholder="e.g. Complete Guide to GCSE Maths Revision" 
                               value="<?= $e($editPost['title'] ?? '') ?>">
                        <div class="form-hint">Descriptive, engaging headline for parents and students.</div>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="blog-slug" class="form-label">URL Slug (Optional)</label>
                        <input type="text" id="blog-slug" name="slug" class="form-input" maxlength="255" 
                               placeholder="leave blank to auto-generate from title" 
                               value="<?= $e($editPost['slug'] ?? '') ?>">
                        <div class="form-hint">Unique alphanumeric slug (e.g. gcse-maths-revision-guide).</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="blog-excerpt" class="form-label">Excerpt / Summary</label>
                    <textarea id="blog-excerpt" name="excerpt" class="form-input" rows="2" maxlength="500"
                              placeholder="Brief summary displayed on article cards and search results..."><?= $e($editPost['excerpt'] ?? '') ?></textarea>
                    <div class="form-hint">Optional summary (up to 500 characters).</div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="blog-body" class="form-label form-label-required">Article Body</label>
                    <textarea id="blog-body" name="body" class="form-input" rows="8" required 
                              placeholder="Write article content here. Safe formatting with line breaks is preserved..."><?= $e($editPost['body'] ?? '') ?></textarea>
                    <div class="form-hint">Server-side escaping and sanitization applied automatically.</div>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editPost ? 'Save Post Changes' : 'Create Draft Post' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <form action="/manager-blog.php" method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="blog-search" class="visually-hidden">Search posts</label>
                    <input type="text" id="blog-search" name="search" class="form-input" 
                           placeholder="Search by title, excerpt, or slug..." 
                           value="<?= $e($search) ?>">
                </div>

                <div>
                    <label for="blog-status-filter" class="visually-hidden">Filter by status</label>
                    <select id="blog-status-filter" name="status" class="form-input" onchange="this.form.submit()">
                        <?php foreach ($statuses as $val => $lbl): ?>
                            <option value="<?= $e($val) ?>" <?= ($filterStatus === $val) ? 'selected' : '' ?>>
                                <?= $e($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <?php if ($filterStatus !== 'ALL' || !empty($search)): ?>
                    <a href="/manager-blog.php" class="btn btn-secondary btn-sm" style="color: var(--color-navy-600);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Blog Posts Table -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Editorial Post Library</h2>
                <span style="font-size: 0.875rem; color: var(--color-navy-500);">
                    Showing <?= count($posts) ?> of <?= $e($pagination['total'] ?? 0) ?> posts
                </span>
            </div>

            <?php if (empty($posts)): ?>
                <div style="padding: 2.5rem; text-align: center; color: var(--color-navy-500);">
                    <p style="font-size: 1.05rem; margin-bottom: 0.5rem;">No blog posts found matching current filters.</p>
                    <p style="font-size: 0.875rem;">Use the form above to compose and publish educational articles.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;" role="table" aria-label="Blog posts list">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); text-align: left; font-size: 0.85rem; color: var(--color-navy-600); text-transform: uppercase;">
                                <th style="padding: 0.75rem 0.5rem;">ID</th>
                                <th style="padding: 0.75rem 0.5rem;">Title &amp; Slug</th>
                                <th style="padding: 0.75rem 0.5rem;">Author</th>
                                <th style="padding: 0.75rem 0.5rem;">Status</th>
                                <th style="padding: 0.75rem 0.5rem;">Published</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Editorial Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $p): 
                                $statusBadgeClass = match($p['status']) {
                                    'PUBLISHED' => 'badge-verified',
                                    'DRAFT' => 'badge-pending',
                                    'ARCHIVED' => 'badge-cancelled',
                                    default => 'badge-secondary'
                                };
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100); font-size: 0.95rem;">
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--color-navy-700);">
                                        #<?= $e($p['id']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div style="font-weight: 600; color: var(--color-navy-950);">
                                            <?= $e($p['title']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500); font-family: monospace;">
                                            /blog/<?= $e($p['slug']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-size: 0.875rem; color: var(--color-navy-700);">
                                        <?= $e($p['author_name'] ?? 'Platform Manager') ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span class="badge <?= $statusBadgeClass ?>">
                                            <?= $e($p['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: var(--color-navy-600);">
                                        <?= $p['published_at'] ? $e($p['published_at']) : '<span style="color: var(--color-navy-400);">&mdash;</span>' ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                            
                                            <!-- View / Preview -->
                                            <?php if ($p['status'] === 'PUBLISHED'): ?>
                                                <a href="/blog-post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="View live public article">
                                                    Live &nearr;
                                                </a>
                                            <?php endif; ?>

                                            <!-- Edit -->
                                            <a href="/manager-blog.php?edit_id=<?= $e($p['id']) ?>" class="btn btn-secondary btn-sm">
                                                Edit
                                            </a>

                                            <!-- Publish action (if not published) -->
                                            <?php if ($p['status'] !== 'PUBLISHED'): ?>
                                                <form action="/manager-blog.php" method="POST" style="display: inline;" onsubmit="return confirm('Publish this article publicly?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="PUBLISH">
                                                    <input type="hidden" name="post_id" value="<?= $e($p['id']) ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        Publish
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Archive action (if not archived) -->
                                            <?php if ($p['status'] !== 'ARCHIVED'): ?>
                                                <form action="/manager-blog.php" method="POST" style="display: inline;" onsubmit="return confirm('Archive this post? It will no longer be visible publicly.');">
                                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="ARCHIVE">
                                                    <input type="hidden" name="post_id" value="<?= $e($p['id']) ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-red-600);">
                                                        Archive
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-navy-200);">
                        <div style="font-size: 0.875rem; color: var(--color-navy-600);">
                            Page <?= $e($pagination['page']) ?> of <?= $e($pagination['total_pages']) ?>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($pagination['page'] > 1): ?>
                                <a href="/manager-blog.php?page=<?= $pagination['page'] - 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                            <?php endif; ?>
                            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                                <a href="/manager-blog.php?page=<?= $pagination['page'] + 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>
