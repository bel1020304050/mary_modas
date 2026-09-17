<?php
require __DIR__.'/includes/bootstrap.php';
$user=require_client();$pdo=db();$items=cart_items((int)$user['id']);
if(!$items){flash('warning','Adicione produtos ao carrinho antes do checkout.');redirect('store.php');}
$methods=$pdo->query("SELECT * FROM payment_methods WHERE status='Ativo' ORDER BY name")->fetchAll();
if(is_post()){
    verify_csrf();
    $methodId=(int)($_POST['payment_method_id']??0);$result=$_POST['simulation_result']??'approved';
    try{
        $pdo->beginTransaction();
        $cart=active_cart((int)$user['id']);
        $stmt=$pdo->prepare("SELECT ci.product_id,ci.quantity,ci.unit_price,p.name,p.stock,p.status FROM cart_items ci 
        JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? FOR UPDATE");
        $stmt->execute([$cart['id']]);$locked=$stmt->fetchAll();
        if(!$locked) throw new RuntimeException('Carrinho vazio.');
        $total=0;foreach($locked as $i){if($i['status']!=='Ativo'||(int)$i['stock']<(int)$i['quantity']) 
        throw new RuntimeException('Estoque insuficiente para '.$i['name'].'.');
        $total+=(float)$i['unit_price']*(int)$i['quantity'];}
        $stmt=$pdo->prepare("SELECT * FROM payment_methods WHERE id=? AND status='Ativo'");
        $stmt->execute([$methodId]);$method=$stmt->fetch();if(!$method) throw new RuntimeException('Forma de pagamento inválida.');
        $pdo->prepare("INSERT INTO orders(user_id,total,status,stock_deducted) VALUES (?,?,'Aguardando Pagamento',0)")->execute([$user['id'],$total]);$orderId=(int)$pdo->lastInsertId();
        $ins=$pdo->prepare("INSERT INTO order_items(order_id,product_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)");foreach($locked as $i){$ins->execute([$orderId,$i['product_id'],$i['quantity'],$i['unit_price'],(float)$i['unit_price']*(int)$i['quantity']]);}
        $pdo->prepare("INSERT INTO order_history(order_id,status,note) VALUES (?,'Aguardando Pagamento','Pedido criado a partir do carrinho')")->execute([$orderId]);
        if($result==='approved'){
            $pdo->prepare("INSERT INTO payments(order_id,payment_method_id,amount,status,paid_at) VALUES (?,?,?,'Aprovado',NOW())")->execute([$orderId,$methodId,$total]);
            $pdo->prepare("UPDATE orders SET status='Pago',stock_deducted=1 WHERE id=?")->execute([$orderId]);
            $upd=$pdo->prepare("UPDATE products SET stock=stock-? WHERE id=?");foreach($locked as $i){$upd->execute([$i['quantity'],$i['product_id']]);}
            $pdo->prepare("UPDATE carts SET status='Finalizado' WHERE id=?")->execute([$cart['id']]);
            $pdo->prepare("INSERT INTO order_history(order_id,status,note) VALUES (?,'Pago','Pagamento simulado aprovado; estoque baixado e carrinho finalizado')")->execute([$orderId]);
            $pdo->commit();flash('success','Pagamento simulado aprovado. Pedido #'.$orderId.' está Pago. Estoque baixado e carrinho finalizado.');redirect('my_orders.php');
        } else {
            $pdo->prepare("INSERT INTO payments(order_id,payment_method_id,amount,status,paid_at) VALUES (?,?,?,'Recusado',NOW())")->execute([$orderId,$methodId,$total]);
            $pdo->prepare("UPDATE orders SET status='Pagamento Recusado' WHERE id=?")->execute([$orderId]);
            $pdo->prepare("INSERT INTO order_history(order_id,status,note) VALUES (?,'Pagamento Recusado','Simulação recusada; estoque e carrinho preservados')")->execute([$orderId]);
            $pdo->commit();flash('danger','Pagamento simulado recusado. O estoque não foi alterado e o carrinho permanece disponível.');redirect('cart.php');
        }
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('danger',$e->getMessage());redirect('checkout.php');}
}
$total=cart_total((int)$user['id']);$pageTitle='Checkout';require __DIR__.'/includes/header.php';
?>
<h1>Finalizar compra</h1><div class="checkout-steps"><span class="step active">1. Gerar pedido: Aguardando Pagamento</span><span class="step">2. Simular pagamento</span><span class="step">3. Pago</span><span class="step">4. Baixar estoque</span><span class="step">5. Finalizar carrinho</span></div>
<div class="grid grid-2"><div class="card"><h2>Resumo</h2><?php foreach($items as $i): ?><p><?=e($i['name'])?> × <?=$i['quantity']?> <strong style="float:right"><?=money($i['quantity']*$i['unit_price'])?></strong></p><?php endforeach; ?><hr><p>Total <strong style="float:right"><?=money($total)?></strong></p><p class="muted">Endereço e frete não fazem parte deste MVP. Entrega/retirada é combinada fora do sistema.</p></div>
<div class="card"><h2>Pagamento simulado</h2><form method="post"><?=csrf_field()?><div class="field"><label>Forma de pagamento</label><select name="payment_method_id" required><?php foreach($methods as $m): ?><option value="<?=$m['id']?>"><?=e($m['name'])?></option><?php endforeach; ?></select></div><div class="field" style="margin-top:14px"><label>Resultado da simulação</label><select name="simulation_result"><option value="approved">Aprovar pagamento</option><option value="declined">Recusar pagamento</option></select></div><button class="btn btn-primary" style="margin-top:16px">Processar simulação</button></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
