<?php
require __DIR__.'/includes/bootstrap.php';$user=require_client();
$stmt=db()->prepare("SELECT o.*,pm.name payment_method,p.status payment_status FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE o.user_id=? ORDER BY o.created_at DESC");$stmt->execute([$user['id']]);$orders=$stmt->fetchAll();
$pageTitle='Meus pedidos';require __DIR__.'/includes/header.php';
?>
<h1>Meus pedidos</h1><p class="lead">O administrador altera os status; a visualização abaixo reflete o estado atual armazenado no sistema.</p>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Data</th><th>Pagamento</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach($orders as $o): ?><tr><td>#<?=$o['id']?></td><td><?=date('d/m/Y H:i',strtotime($o['created_at']))?></td><td><?=e($o['payment_method']??'-')?> <?=e($o['payment_status']??'')?></td><td><?=money($o['total'])?></td><td><?=status_badge($o['status'])?></td></tr><?php endforeach; ?></tbody></table></div><?php if(!$orders):?><div class="empty">Nenhum pedido ainda.</div><?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
