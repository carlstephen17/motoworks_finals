<?php
// components/featured_products.php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/config.php';

$featured_products = $products ?? [];

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT productId as id, productName as name, productPrice as price, productStock as stock, productImage as image, productActive as active, productId, productName, productPrice, productStock, productImage, productActive FROM product WHERE productActive = 1 ORDER BY productId ASC LIMIT 8
        ");

        $databaseProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($databaseProducts)) {
            $featured_products = $databaseProducts;
        }

    } catch (Exception $e) {
        error_log('Unable to load featured products: ' . $e->getMessage());
        $featured_products = $products ?? [];
    }
}
?>

<!-- ==================== FEATURED PRODUCTS SECTION ==================== -->
<section id="products" class="container section-padding-top">

    <div class="section-title">
        <h2>Featured Products</h2>
    </div>

    <?php if (!empty($featured_products)): ?>

        <div class="card-grid product-grid" id="featured-product-grid">

            <?php foreach ($featured_products as $productIndex => $p): ?>

                <?php
                $productId = (int)$p['id'];

                $productName = $p['name'] ?? 'Product';

                $productPrice = is_numeric($p['price'])
                    ? (float)$p['price']
                    : 0;

                $priceFormatted = '₱' . number_format($productPrice, 2);

                $description = $p['description'] ?? '';

                $image = trim((string) ($p['image'] ?? ''));
                if ($image !== '' && basename(str_replace('\\', '/', $image)) === $image) {
                    $image = 'uploads/' . $image;
                }
                $image = ltrim(str_replace(['\\', '../'], ['/', ''], $image), '/');
                if ($image === '' || !is_file(__DIR__ . '/../' . $image)) {
                    $image = '';
                }

                /*
                 * Detect the stock column used by your products table.
                 */
                $stockLevel = 0;
                $stockKnown = false;

                foreach (['stock_level', 'stock', 'quantity', 'qty'] as $col) {
                    if (isset($p[$col])) {
                        $stockLevel = (int)$p[$col];
                        $stockKnown = true;
                        break;
                    }
                }

                /*
                 * Safely prepare values for JavaScript.
                 */
                $jsProductName = json_encode($productName);
                $jsProductPrice = json_encode($productPrice);
                ?>

                <!-- PRODUCT CARD -->
                <div
                    class="product-card"
                    data-name="<?= strtolower(htmlspecialchars($productName, ENT_QUOTES)) ?>"
                    style="
                        background: rgba(255, 255, 255, 0.03);
                        border: 1px solid rgba(255, 255, 255, 0.08);
                        border-radius: 8px;
                        padding: 25px;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                    "
                >

                    <!-- PRODUCT IMAGE -->
                    <div
                        class="product-image"
                        style="
                            height: 160px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 15px;
                        "
                    >
                        <?php if ($image !== ''): ?>
                            <img
                                src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>"
                                style="max-width: 100%; max-height: 160px; object-fit: contain;"
                            >
                        <?php else: ?>
                            <i class="fa-solid fa-box-open" aria-hidden="true" style="font-size: 3rem; color: var(--color-text-muted, #a0a0a0);"></i>
                        <?php endif; ?>
                    </div>

                    <!-- PRODUCT NAME -->
                    <h3
                        class="product-name"
                        style="
                            color: #fff;
                            margin-bottom: 8px;
                        "
                    >
                        <?= htmlspecialchars($productName) ?>
                    </h3>

                    <!-- PRODUCT DESCRIPTION -->
                    <?php if (!empty($description)): ?>
                        <p
                            class="product-description"
                            style="
                                color: rgba(255, 255, 255, 0.65);
                                font-size: 0.9rem;
                                margin-bottom: 10px;
                            "
                        >
                            <?= htmlspecialchars($description) ?>
                        </p>
                    <?php endif; ?>

                    <!-- PRODUCT PRICE -->
                    <div
                        class="product-price"
                        style="
                            color: #fff;
                            font-size: 1.1rem;
                            font-weight: 600;
                            margin-bottom: 5px;
                        "
                    >
                        <?= $priceFormatted ?>
                    </div>

                    <!-- STOCK -->
                    <div
                        class="product-stock"
                        style="
                            font-size: 0.85rem;
                            margin: 5px 0 15px 0;
                            color: <?= ($stockKnown && $stockLevel > 0) ? '#2e7d32' : '#c62828' ?>;
                        "
                    >
                        <?php if ($stockKnown): ?>
                            Stock: <strong><?= $stockLevel ?></strong> available
                        <?php else: ?>
                            Inventory not listed
                        <?php endif; ?>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div
                        class="product-card-actions"
                        style="
                            display: flex;
                            gap: 10px;
                        "
                    >

                        <!-- ADD TO CART -->
                        <?php if ($stockKnown && $stockLevel > 0): ?>

                            <button
                                type="button"
                                class="btn btn-outline btn-cart-add"
                                title="Add to Cart"
                                onclick='openCartModal(
                                    <?= $productId ?>,
                                    <?= $jsProductName ?>,
                                    <?= $jsProductPrice ?>,
                                    <?= $stockLevel ?>
                                )'
                                style="
                                    flex: 0 0 50px;
                                    width: 50px;
                                "
                            >
                                <i class="fa-solid fa-cart-plus"></i>
                            </button>

                        <?php else: ?>

                            <button
                                type="button"
                                class="btn btn-outline"
                                disabled
                                title="Out of Stock"
                                style="
                                    flex: 0 0 50px;
                                    width: 50px;
                                    opacity: 0.5;
                                    cursor: not-allowed;
                                "
                            >
                                <i class="fa-solid fa-cart-plus"></i>
                            </button>

                        <?php endif; ?>


                        <!-- BUY NOW -->
                        <?php if ($stockKnown && $stockLevel > 0): ?>

                            <button
                                type="button"
                                class="btn btn-primary btn-block"
                                onclick='openBuyModal(
                                    <?= $productId ?>,
                                    <?= $jsProductName ?>,
                                    <?= $jsProductPrice ?>,
                                    <?= $stockLevel ?>
                                )'
                                style="flex: 1;"
                            >
                                Buy Now
                            </button>

                        <?php else: ?>

                            <button
                                type="button"
                                class="btn btn-secondary btn-block"
                                disabled
                                style="
                                    flex: 1;
                                    background: #ccc;
                                    cursor: not-allowed;
                                    border-color: #ccc;
                                "
                            >
                                <?= $stockKnown ? 'Out of Stock' : 'Unavailable' ?>
                            </button>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p
            style="
                text-align: center;
                color: rgba(255, 255, 255, 0.6);
                padding: 30px;
            "
        >
            No featured products available at the moment.
        </p>

    <?php endif; ?>

</section>