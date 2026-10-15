<?php
/** @var bool $isEdit */
/** @var ?array $product */
/** @var array $values */
/** @var array $errors */
/** @var array $categories */

$value = static function (string $key, string $default = '') use ($values) {
    $v = $values[$key] ?? $default;
    return $v === null ? '' : (string) $v;
};
?>

<section class="card narrow">
    <div class="card-head">
        <h2><?= $isEdit ? 'Edit product' : 'Add a new product' ?></h2>
        <a class="btn btn-ghost" href="<?= e(url('products')) ?>">Back to list</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error">Please correct the <?= count($errors) ?> highlighted field<?= count($errors) === 1 ? '' : 's' ?>.</div>
    <?php endif; ?>

    <form method="post" class="form"
          action="<?= e($isEdit ? url('product_edit', ['id' => $product['id']]) : url('product_new')) ?>">
        <?= csrf_field() ?>

        <div class="field-row">
            <div class="field">
                <label for="sku">Product code (SKU)</label>
                <input id="sku" name="sku" type="text" value="<?= e($value('sku')) ?>" required maxlength="32">
                <?php if (isset($errors['sku'])): ?><p class="field-error"><?= e($errors['sku']) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="0">Uncategorised</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"
                            <?= (string) $value('category_id') === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="name">Product name</label>
            <input id="name" name="name" type="text" value="<?= e($value('name')) ?>" required maxlength="120">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="price">Unit price (<?= e(currency()) ?>)</label>
                <input id="price" name="price" type="number" step="0.01" min="0" value="<?= e($value('price')) ?>" required>
                <?php if (isset($errors['price'])): ?><p class="field-error"><?= e($errors['price']) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="quantity">Quantity in stock</label>
                <input id="quantity" name="quantity" type="number" step="1" min="0" value="<?= e($value('quantity')) ?>" required>
                <?php if (isset($errors['quantity'])): ?><p class="field-error"><?= e($errors['quantity']) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="reorder_level">Reorder level</label>
                <input id="reorder_level" name="reorder_level" type="number" step="1" min="0" value="<?= e($value('reorder_level', '5')) ?>" required>
                <?php if (isset($errors['reorder_level'])): ?><p class="field-error"><?= e($errors['reorder_level']) ?></p><?php endif; ?>
                <p class="hint">A low-stock warning appears at or below this quantity.</p>
            </div>
        </div>

        <fieldset class="field">
            <legend>Product type</legend>
            <?php foreach (PRODUCT_TYPES as $key => $label): ?>
                <label class="radio">
                    <input type="radio" name="type" value="<?= e($key) ?>"
                           data-type-toggle
                        <?= $value('type', 'standard') === $key ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
            <p class="hint">Perishable products carry an expiry date and appear in the expiry warnings.</p>
        </fieldset>

        <div class="field" id="expiry-field" <?= $value('type', 'standard') === 'perishable' ? '' : 'hidden' ?>>
            <label for="expiry_date">Expiry date</label>
            <input id="expiry_date" name="expiry_date" type="date" value="<?= e($value('expiry_date')) ?>">
            <?php if (isset($errors['expiry_date'])): ?><p class="field-error"><?= e($errors['expiry_date']) ?></p><?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('products')) ?>">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit && $product !== null): ?>
        <p class="muted small">
            Created <?= e(date('d M Y H:i', strtotime((string) $product['created_at']))) ?>
            &middot; last updated <?= e(date('d M Y H:i', strtotime((string) $product['updated_at']))) ?>
        </p>
    <?php endif; ?>
</section>
