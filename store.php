<?php
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if (is_post()) {
    $user = require_client();
    verify_csrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $stmt = db()->prepare("SELECT id,name,price,stock,status FROM products WHERE id=? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['status'] !== 'Ativo' || (int)$product['stock'] < $qty) {
        flash('danger', 'Produto indisponível ou quantidade acima do estoque.');
    } else {
        $cart = active_cart((int)$user['id']);
        $stmt = db()->prepare("SELECT id,quantity FROM cart_items WHERE cart_id=? AND product_id=?");
        $stmt->execute([$cart['id'],$productId]);
        $existing = $stmt->fetch();
        $newQty = $qty + (int)($existing['quantity'] ?? 0);
        if ($newQty > (int)$product['stock']) {
            flash('danger', 'Quantidade total no carrinho ultrapassa o estoque disponível.');
        } elseif ($existing) {
            db()->prepare("UPDATE cart_items SET quantity=?, unit_price=? WHERE id=?")->execute([$newQty,$product['price'],$existing['id']]);
            flash('success', 'Quantidade atualizada no carrinho.');
        } else {
            db()->prepare("INSERT INTO cart_items(cart_id,product_id,quantity,unit_price) VALUES (?,?,?,?)")->execute([$cart['id'],$productId,$qty,$product['price']]);
            flash('success', 'Produto adicionado ao carrinho.');
        }
    }
    redirect('store.php');
}
$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, c.name category_name, s.name subgroup_name, b.name brand_name FROM products p JOIN categories c ON c.id=p.category_id JOIN subgroups s ON s.id=p.subgroup_id JOIN brands b ON b.id=p.brand_id WHERE p.status='Ativo' AND p.stock > 0";
$params=[];
if ($search !== '') { $sql .= " AND LOWER(p.name) LIKE LOWER(?)"; $params[]='%'.$search.'%'; }
$sql .= " ORDER BY p.created_at DESC";
$stmt=db()->prepare($sql);$stmt->execute($params);$products=$stmt->fetchAll();
$pageTitle='Loja';require __DIR__.'/includes/header.php';
?>
<section class="hero">
  <div><span class="pill">MVP de validação do TCC</span><h1>Mary Modas</h1><p>Catálogo, carrinho, pagamento simulado, estoque e acompanhamento de pedidos.</p></div>
  <div><strong><?= count($products) ?></strong><br><span class="muted">produtos disponíveis</span></div>
</section>
<form class="toolbar no-print" method="get"><div class="field grow"><label>Buscar produtos</label><input name="q" value="<?=e($search)?>" placeholder="Ex.: vestido"></div><button class="btn btn-light">Pesquisar</button></form>
<div class="products">
<?php foreach($products as $p): ?>
  <article class="product-card">
    <div class="product-image"><?php if($p['image_url']): ?><img src="<?=e($p['image_url'])?>" alt=""><?php else: ?>◇<?php endif; ?></div>
    <div class="product-body"><div class="product-title"><?=e($p['name'])?></div><div class="muted"><?=e($p['category_name'])?> · <?=e($p['subgroup_name'])?> · <?=e($p['brand_name'])?></div><div class="price"><?=money($p['price'])?></div><div class="stock">Em estoque: <?=e($p['stock'])?></div>
    <?php if((float)$p['promotion_percent']>0): ?><span class="badge badge-warning"><?=e($p['promotion_percent'])?>% promoção</span><?php endif; ?>
    <?php if($user && $user['profile']==='CLIENTE'): ?><form method="post" class="toolbar" style="margin-bottom:0"><?=csrf_field()?><input type="hidden" name="product_id" value="<?=$p['id']?>"><div class="field" style="width:80px"><label>Qtd.</label><input type="number" name="quantity" value="1" min="1" max="<?=$p['stock']?>"></div><button class="btn btn-primary">Adicionar</button></form><?php elseif(!$user): ?><a class="btn btn-primary" style="margin-top:12px" href="<?=e(url('login.php'))?>">Entrar para comprar</a><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if(!$products): ?><div class="card empty">Nenhum produto encontrado.</div><?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
