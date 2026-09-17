<?php
require __DIR__.'/includes/bootstrap.php';
$user=require_client();
if(is_post()){
    verify_csrf();
    $action=$_POST['action']??'';$itemId=(int)($_POST['item_id']??0);
    if($action==='remove') db()->prepare("DELETE FROM cart_items WHERE id=? AND 
    cart_id=(SELECT id FROM carts WHERE user_id=? AND status='Ativo' ORDER BY id DESC LIMIT 1)")->execute([$itemId,$user['id']]);
    if($action==='update'){
        $qty=max(1,(int)($_POST['quantity']??1));
        $stmt=db()->prepare("SELECT ci.product_id,p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id 
        JOIN carts c ON c.id=ci.cart_id WHERE ci.id=? AND c.user_id=? AND c.status='Ativo'");
        $stmt->execute([$itemId,$user['id']]);$row=$stmt->fetch();
        if($row && $qty<=(int)$row['stock']) db()->prepare("UPDATE cart_items SET quantity=? WHERE id=?")->execute([$qty,$itemId]); 
        else flash('danger','Quantidade acima do estoque.');
    }
    redirect('cart.php');
}
$items=cart_items((int)$user['id']);$total=cart_total((int)$user['id']);
$pageTitle='Carrinho';require __DIR__.'/includes/header.php';
?>
<h1>Carrinho</h1><p class="lead">O estoque será baixado somente depois da aprovação do pagamento simulado.</p>
<div class="card">
<?php if(!$items): ?><div class="empty">Seu carrinho está vazio.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th>Preço</th><th>Quantidade</th><th>Subtotal</th><th></th></tr></thead><tbody>
<?php foreach($items as $i): ?><tr><td><?=e($i['name'])?></td><td><?=money($i['unit_price'])?></td><td><form method="post" 
    class="actions"><?=csrf_field()?><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?=$i['id']?>"><input style="width:70px" type="number" name="quantity" min="1" max="<?=$i['stock']?>" value="<?=$i['quantity']?>"><button class="btn btn-light btn-sm">Atualizar</button></form></td><td><?=money($i['quantity']*$i['unit_price'])?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?=$i['id']?>"><button class="btn btn-danger btn-sm">Remover</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="toolbar" style="justify-content:flex-end"><strong>Total: <?=money($total)?></strong><a class="btn btn-primary" href="<?=e(url('checkout.php'))?>">Finalizar compra</a></div>
<?php endif; ?>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
