<!-- Single Blog Post Reader View -->
<div class="container section">
    <div style="max-width: 820px; margin: 0 auto;">
        <!-- Breadcrumb navigation -->
        <nav aria-label="Breadcrumb" style="margin-bottom: 2rem; font-size: 0.875rem;">
            <ol style="display: flex; gap: 0.5rem; list-style: none; margin: 0; padding: 0; color: var(--color-navy-500);">
                <li><a href="/" style="color: var(--color-navy-600); text-decoration: none;">Home</a></li>
                <li aria-hidden="true">&rsaquo;</li>
                <li><a href="/blog.php" style="color: var(--color-navy-600); text-decoration: none;">Blog</a></li>
                <li aria-hidden="true">&rsaquo;</li>
                <li aria-current="page" style="color: var(--color-navy-900); font-weight: 600;"><?= e($post['title']) ?></li>
            </ol>
        </nav>

        <article>
            <header style="margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid var(--color-navy-200);">
                <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap;">
                    <span class="badge badge-primary">Revision Guide</span>
                    <time datetime="<?= e($post['published_at'] ?? '') ?>" style="font-size: 0.875rem; color: var(--color-navy-500);">
                        Published: <?= e($post['published_date_london'] ?? 'Recently') ?>
                    </time>
                    <span style="color: var(--color-navy-400);">&bull;</span>
                    <span style="font-size: 0.875rem; color: var(--color-navy-600); font-weight: 600;">
                        Author: <?= e($post['author_name'] ?? 'Approved Educator') ?>
                    </span>
                </div>
                <h1 style="font-size: clamp(2rem, 3.5vw + 1rem, 2.75rem); line-height: 1.25; margin-bottom: 1.25rem;">
                    <?= e($post['title']) ?>
                </h1>
                <?php if (!empty($post['excerpt'])): ?>
                    <p style="font-size: 1.2rem; color: var(--color-navy-600); line-height: 1.6; font-style: italic;">
                        <?= e($post['excerpt']) ?>
                    </p>
                <?php endif; ?>
            </header>

            <!-- Sanitized Article Content -->
            <div class="article-content" style="font-size: 1.05rem; line-height: 1.8; color: var(--color-navy-800);">
                <?= nl2br(e($post['body'])) ?>
            </div>

            <!-- Footer / Author / Back button -->
            <footer style="margin-top: 3.5rem; padding-top: 2rem; border-top: 1px solid var(--color-navy-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <a href="/blog.php" class="btn btn-outline">&larr; Back to All Revision Guides</a>
                <a href="/tutors.php" class="btn btn-primary">Find a Specialist Tutor in This Subject &rarr;</a>
            </footer>
        </article>
    </div>
</div>
