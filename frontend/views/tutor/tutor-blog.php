<!-- Tutor Blog Authoring & Review Interface -->
<div class="container section">
    <div style="max-width: 960px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <a href="/tutor-profile.php">Tutor Portal</a> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Blog Articles</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Tutor Educational Articles</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Author educational revision articles and submit them for Manager review and public website publication.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="/tutor-bookings.php" class="btn btn-outline btn-sm">Lesson Requests</a>
                    <a href="/tutor-availability.php" class="btn btn-outline btn-sm">Availability</a>
                </div>
            </div>
        </div>

        <!-- Success & Error Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Success:</span>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Create New Article Form Card -->
        <div class="card" style="padding: 2rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md); border-top: 4px solid var(--color-primary-500);">
            <h2 style="font-size: 1.25rem; color: var(--color-navy-950); margin-bottom: 1.25rem;">
                Compose New Blog Article
            </h2>

            <form action="/tutor-blog.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="create_post">

                <div style="margin-bottom: 1rem;">
                    <label for="post-title" style="display: block; font-weight: 600; font-size: 0.875rem; color: var(--color-navy-800); margin-bottom: 0.35rem;">
                        Article Title *
                    </label>
                    <input type="text" id="post-title" name="title" required placeholder="e.g. Master GCSE Chemistry: Top 5 Revision Strategies" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm); font-size: 0.95rem;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label for="post-excerpt" style="display: block; font-weight: 600; font-size: 0.875rem; color: var(--color-navy-800); margin-bottom: 0.35rem;">
                        Excerpt / Summary (Optional)
                    </label>
                    <textarea id="post-excerpt" name="excerpt" rows="2" placeholder="Short introductory summary for preview cards..." style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm); font-size: 0.9rem;"></textarea>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label for="post-body" style="display: block; font-weight: 600; font-size: 0.875rem; color: var(--color-navy-800); margin-bottom: 0.35rem;">
                        Article Content *
                    </label>
                    <textarea id="post-body" name="body" rows="6" required placeholder="Write your educational insights, study guides, and subject revision notes here..." style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm); font-size: 0.95rem;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" id="btn-save-draft">
                        Save as Draft Article
                    </button>
                </div>
            </form>
        </div>

        <!-- Tutor Articles List Card -->
        <div class="card" style="padding: 2rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <h2 style="font-size: 1.25rem; color: var(--color-navy-950); margin-bottom: 1.25rem;">
                My Articles &amp; Moderation Queue (<?= count($posts) ?>)
            </h2>

            <?php if (empty($posts)): ?>
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--color-navy-500); background: var(--color-navy-50); border-radius: var(--radius-md); border: 1px dashed var(--color-navy-200);">
                    <p style="margin: 0; font-size: 0.95rem;">You haven't authored any blog articles yet. Use the form above to share your academic expertise with students and parents.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($posts as $p): 
                        $statusBadge = match($p['status']) {
                            'PUBLISHED' => 'badge-verified',
                            'APPROVED' => 'badge-verified',
                            'SUBMITTED' => 'badge-primary',
                            'REJECTED' => 'badge-suspended',
                            default => 'badge-primary'
                        };
                    ?>
                        <div style="padding: 1.25rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <strong style="font-size: 1.05rem; color: var(--color-navy-950);"><?= e($p['title']) ?></strong>
                                    <span class="badge <?= $statusBadge ?>"><?= e($p['status']) ?></span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--color-navy-500);">
                                    Created: <?= e(substr($p['created_at'] ?? '', 0, 16)) ?>
                                    <?php if (!empty($p['slug'])): ?>
                                        &bull; /blog/<?= e($p['slug']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($p['excerpt'])): ?>
                                    <div style="font-size: 0.875rem; color: var(--color-navy-600); margin-top: 0.35rem;">
                                        <?= e($p['excerpt']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <?php if ($p['status'] === 'DRAFT' || $p['status'] === 'REJECTED'): ?>
                                    <!-- Submit for Manager Review -->
                                    <form action="/tutor-blog.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="submit_post">
                                        <input type="hidden" name="post_id" value="<?= e((string)$p['id']) ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-review-<?= e((string)$p['id']) ?>">
                                            Submit for Manager Review &rarr;
                                        </button>
                                    </form>
                                <?php elseif ($p['status'] === 'SUBMITTED'): ?>
                                    <span style="font-size: 0.85rem; color: var(--color-navy-500); font-style: italic;">
                                        Awaiting Manager Moderation
                                    </span>
                                <?php elseif ($p['status'] === 'APPROVED'): ?>
                                    <span style="font-size: 0.85rem; color: var(--color-emerald-600); font-weight: 600;">
                                        Approved for Publishing
                                    </span>
                                <?php elseif ($p['status'] === 'PUBLISHED'): ?>
                                    <a href="/blog-post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">
                                        View Public Article &nearr;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
