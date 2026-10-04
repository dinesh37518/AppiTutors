<!-- Educational Blog & Revision Guides Index -->
<div class="container section">
    <div style="max-width: 960px; margin: 0 auto;">
        <div style="margin-bottom: 3.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Study Advice & Insights</span>
            <h1 style="margin-bottom: 1rem;">UK Educational Blog & Revision Guides</h1>
            <p style="font-size: 1.15rem; color: var(--color-navy-600); max-width: 720px; margin: 0 auto;">
                Expert advice from verified UK educators on exam technique, revision strategies, and subject masterclasses for Primary, GCSE, and A-Level curricula.
            </p>
        </div>

        <?php if (empty($posts)): ?>
            <!-- Empty State -->
            <div class="card" style="text-align: center; padding: 4rem 2rem; background: var(--color-navy-50);">
                <div class="card-icon" style="margin: 0 auto 1.5rem;" aria-hidden="true">&#128214;</div>
                <h2 style="font-size: 1.4rem; margin-bottom: 0.75rem;">Revision Guides Under Editorial Preparation</h2>
                <p style="color: var(--color-navy-600); max-width: 540px; margin: 0 auto 2rem; font-size: 1rem;">
                    Our approved educators are currently drafting syllabus-aligned revision guides for GCSE and A-Level exam preparation. New articles will appear here following manager editorial review.
                </p>
                <div>
                    <a href="/newsletter.php" class="btn btn-primary">Subscribe for First Access to Guides &rarr;</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Blog Posts Grid -->
            <div class="grid-3" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($posts as $post): ?>
                    <article class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="badge badge-primary">Revision Guide</span>
                            <time datetime="<?= e($post['published_at'] ?? '') ?>" style="font-size: 0.8rem; color: var(--color-navy-500);">
                                <?= e($post['published_date_london'] ?? 'Recently Published') ?>
                            </time>
                        </div>
                        <h2 style="font-size: 1.3rem; margin-bottom: 0.75rem; line-height: 1.35;">
                            <a href="/blog-post.php?slug=<?= urlencode($post['slug']) ?>" style="color: var(--color-navy-950); text-decoration: none;">
                                <?= e($post['title']) ?>
                            </a>
                        </h2>
                        <p class="card-body">
                            <?= e($post['excerpt'] ?? substr(strip_tags($post['body'] ?? ''), 0, 160) . '...') ?>
                        </p>
                        <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--color-navy-200); display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.85rem; color: var(--color-navy-600); font-weight: 600;">
                                By <?= e($post['author_name'] ?? 'Editorial Educator') ?>
                            </span>
                            <a href="/blog-post.php?slug=<?= urlencode($post['slug']) ?>" class="btn btn-outline btn-sm">Read Article &rarr;</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
