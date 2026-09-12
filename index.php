<?php

declare(strict_types=1);
session_start();
require __DIR__ . '/lib.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
}
function post(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }
function getq(string $key, string $default=''): string { return trim((string)($_GET[$key] ?? $default)); }

$page = $_GET['page'] ?? (current_user() ? 'home' : 'login');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'login') {
        check_csrf();
        $email = strtolower(post('email'));
        $password = post('password');
        $found = null;
        foreach (load_json('users') as $u) {
            if (strtolower($u['email']) === $email) { $found = $u; break; }
        }
        if (!$found || !password_verify($password, $found['password_hash']) || $found['status'] !== 'Ativo') {
            throw new RuntimeException('Credenciais inválidas ou conta inativa.');
        }
        $_SESSION['user_id'] = $found['id'];
        flash('success', 'Bem-vindo(a), '.$found['name'].'!');
        redirect('index.php');
    }
    if ($action === 'logout') {
        session_destroy();
        session_start();
        flash('success', 'Sessão encerrada.');
        redirect('index.php?page=login');
    }
    if ($action === 'register') {
        check_csrf();
        $users = load_json('users');
        $email = strtolower(post('email'));
        foreach ($users as $u) if (strtolower($u['email']) === $email) throw new RuntimeException('Este e-mail já está em uso.');
        if (strlen(post('password')) < 8) throw new RuntimeException('A senha deve ter pelo menos 8 caracteres.');
        if (post('password') !== post('password_confirm')) throw new RuntimeException('A confirmação de senha não confere.');
        foreach (['name','email','cep','street','number'] as $f) if (post($f) === '') throw new RuntimeException('Preencha todos os campos obrigatórios.');
        $users[] = [
            'id'=>next_id($users),'name'=>post('name'),'email'=>$email,'phone'=>post('phone'),
            'password_hash'=>password_hash(post('password'), PASSWORD_DEFAULT),'profile'=>'Cliente','status'=>'Ativo',
            'cep'=>post('cep'),'street'=>post('street'),'number'=>post('number'),'created_at'=>now_iso(),
        ];
        save_json('users', $users);
        flash('success','Conta criada. Faça login para continuar.');
        redirect('index.php?page=login');
    }

    if ($action === 'reset_demo') {
        $u = require_admin(); check_csrf(); reset_seed_data(); $_SESSION['user_id'] = 1;
        flash('success','Dados de demonstração restaurados.'); redirect('index.php');
    }

    if ($action === 'profile_save') {
        $u = require_login(); check_csrf();
        $users = load_json('users');
        $email = strtolower(post('email'));
        foreach ($users as $other) {
            if ((int)$other['id'] !== (int)$u['id'] && strtolower($other['email']) === $email) throw new RuntimeException('Este e-mail já está em uso.');
        }
        update_row('users',(int)$u['id'],function(array $row) use ($email): array {
            $row['name']=post('name'); $row['email']=$email; $row['phone']=post('phone');
            $row['cep']=post('cep'); $row['street']=post('street'); $row['number']=post('number');
            if (post('new_password') !== '') {
                if (strlen(post('new_password')) < 8) throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
                $row['password_hash']=password_hash(post('new_password'), PASSWORD_DEFAULT);
            }
            return $row;
        });
        flash('success','Perfil atualizado.'); redirect('index.php?page=profile');
    }

    if ($action === 'admin_user_update') {
        $admin = require_admin(); check_csrf();
        $id=(int)post('id'); $target=find_by_id('users',$id); if(!$target) throw new RuntimeException('Usuário não encontrado.');
        if ($id === (int)$admin['id'] && post('profile') !== $admin['profile']) throw new RuntimeException('Você não pode alterar o próprio perfil.');
        update_row('users',$id,function(array $r) use ($admin): array {
            if ((int)$r['id'] !== (int)$admin['id']) $r['profile']=post('profile');
            return $r;
        });
        flash('success','Perfil do usuário atualizado.'); redirect('index.php?page=users');
    }
    if ($action === 'admin_user_toggle') {
        $admin=require_admin(); check_csrf(); $id=(int)post('id'); $target=find_by_id('users',$id); if(!$target) throw new RuntimeException('Usuário não encontrado.');
        if ($id === (int)$admin['id']) throw new RuntimeException('Administrador não pode desativar a própria conta.');
        if ($target['status']==='Ativo' && !can_deactivate_user($target)) throw new RuntimeException('O usuário possui pedido em andamento e não pode ser desativado.');
        update_row('users',$id,fn($r)=>array_merge($r,['status'=>$r['status']==='Ativo'?'Inativo':'Ativo']));
        flash('success','Status do usuário alterado.'); redirect('index.php?page=users');
    }

    // CRUD genérico de categorias
    if ($action === 'category_save') {
        require_admin(); check_csrf(); $rows=load_json('categories'); $id=(int)post('id'); $name=post('name');
        if($name==='') throw new RuntimeException('Nome da categoria é obrigatório.');
        foreach($rows as $r) if(strcasecmp($r['name'],$name)===0 && (int)$r['id']!==$id) throw new RuntimeException('Já existe uma categoria com este nome.');
        if($id){ update_row('categories',$id,fn($r)=>array_merge($r,['name'=>$name,'description'=>post('description')])); }
        else { $rows[]=['id'=>next_id($rows),'name'=>$name,'description'=>post('description'),'status'=>'Ativo']; save_json('categories',$rows); }
        flash('success','Categoria salva.'); redirect('index.php?page=categories');
    }
    if ($action === 'category_toggle') {
        require_admin(); check_csrf(); $id=(int)post('id'); $cat=find_by_id('categories',$id); if(!$cat) throw new RuntimeException('Categoria não encontrada.');
        if($cat['status']==='Ativo') foreach(load_json('products') as $p) if((int)$p['category_id']===$id && $p['status']==='Ativo') throw new RuntimeException('Inative ou recategorize os produtos ativos antes de inativar a categoria.');
        update_row('categories',$id,fn($r)=>array_merge($r,['status'=>$r['status']==='Ativo'?'Inativo':'Ativo']));
        flash('success','Status da categoria alterado.'); redirect('index.php?page=categories');
    }

    if ($action === 'supplier_save') {
        require_admin(); check_csrf(); $rows=load_json('suppliers'); $id=(int)post('id'); $doc=post('document');
        if(post('name')==='') throw new RuntimeException('Nome/Razão social é obrigatório.');
        if($doc!=='') foreach($rows as $r) if($r['document']===$doc && (int)$r['id']!==$id) throw new RuntimeException('CPF/CNPJ já cadastrado.');
        $data=['name'=>post('name'),'document'=>$doc,'phone'=>post('phone'),'email'=>post('email')];
        if($id){ update_row('suppliers',$id,fn($r)=>array_merge($r,$data)); }
        else { $data['id']=next_id($rows);$data['status']='Ativo';$rows[]=$data;save_json('suppliers',$rows); }
        flash('success','Fornecedor salvo.'); redirect('index.php?page=suppliers');
    }
    if ($action === 'supplier_toggle') {
        require_admin(); check_csrf(); $id=(int)post('id'); update_row('suppliers',$id,fn($r)=>array_merge($r,['status'=>$r['status']==='Ativo'?'Inativo':'Ativo']));
        flash('success','Status do fornecedor alterado.'); redirect('index.php?page=suppliers');
    }

    if ($action === 'product_save') {
        require_admin(); check_csrf(); $rows=load_json('products'); $id=(int)post('id');
        $cat=find_by_id('categories',(int)post('category_id')); if(!$cat || $cat['status']!=='Ativo') throw new RuntimeException('Selecione uma categoria ativa.');
        $sup=null; if(post('supplier_id')!==''){ $sup=find_by_id('suppliers',(int)post('supplier_id')); if(!$sup || $sup['status']!=='Ativo') throw new RuntimeException('Selecione um fornecedor ativo.'); }
        $price=(float)str_replace(',','.',post('price')); $stock=(int)post('stock'); if($price<=0||$stock<0) throw new RuntimeException('Preço deve ser maior que zero e estoque não pode ser negativo.');
        $name=post('name');$size=post('size');$color=post('color');$catId=(int)post('category_id');
        foreach($rows as $r){ if((int)$r['id']!==$id && strcasecmp($r['name'],$name)===0 && (int)$r['category_id']===$catId && strcasecmp($r['size'],$size)===0 && strcasecmp($r['color'],$color)===0) throw new RuntimeException('Já existe produto com a mesma combinação de nome, categoria, tamanho e cor.'); }
        $data=['name'=>$name,'description'=>post('description'),'category_id'=>$catId,'supplier_id'=>$sup?(int)$sup['id']:null,'price'=>$price,'size'=>$size?:'Único','color'=>$color?:'Não se aplica','stock'=>$stock,'image'=>post('image')];
        if($id){update_row('products',$id,fn($r)=>array_merge($r,$data));}else{$data['id']=next_id($rows);$data['status']='Ativo';$rows[]=$data;save_json('products',$rows);}
        flash('success','Produto salvo.'); redirect('index.php?page=products');
    }
    if ($action === 'product_toggle') { require_admin();check_csrf();$id=(int)post('id');update_row('products',$id,fn($r)=>array_merge($r,['status'=>$r['status']==='Ativo'?'Inativo':'Ativo']));flash('success','Status do produto alterado.');redirect('index.php?page=products'); }

    if ($action === 'promotion_save') {
        require_admin();check_csrf();$rows=load_json('promotions');$id=(int)post('id');$mode=post('mode');$code=strtoupper(post('code'));
        if($mode==='Automática') $code='';
        if($mode==='Cupom' && $code==='') throw new RuntimeException('Cupom exige código.');
        foreach($rows as $r) if($code!=='' && strtoupper($r['code'])===$code && (int)$r['id']!==$id) throw new RuntimeException('Código de cupom já cadastrado.');
        $value=(float)str_replace(',','.',post('discount_value'));$type=post('discount_type'); if($value<=0 || ($type==='Percentual' && $value>50)) throw new RuntimeException('Percentual deve ficar entre 1% e 50%; valor deve ser maior que zero.');
        if(post('end_date')<post('start_date')) throw new RuntimeException('Data final não pode ser anterior à data inicial.');
        $application=post('application');$target=null;if($application==='Produto')$target=(int)post('product_target');if($application==='Categoria')$target=(int)post('category_target');
        $data=['name'=>post('name'),'mode'=>$mode,'code'=>$code,'discount_type'=>$type,'discount_value'=>$value,'start_date'=>post('start_date'),'end_date'=>post('end_date'),'application'=>$application,'target_id'=>$target];
        if($id){$existing=find_by_id('promotions',$id); if($existing && $existing['used']){$data['mode']=$existing['mode'];$data['code']=$existing['code'];} update_row('promotions',$id,fn($r)=>array_merge($r,$data));}
        else{$data['id']=next_id($rows);$data['status']='Ativa';$data['used']=false;$rows[]=$data;save_json('promotions',$rows);}
        flash('success','Promoção salva.');redirect('index.php?page=promotions');
    }
    if ($action === 'promotion_toggle'){require_admin();check_csrf();$id=(int)post('id');update_row('promotions',$id,fn($r)=>array_merge($r,['status'=>$r['status']==='Ativa'?'Inativa':'Ativa']));flash('success','Status da promoção alterado.');redirect('index.php?page=promotions');}

    if ($action === 'cart_add') {
        $u=require_login();check_csrf();if($u['profile']!=='Cliente') throw new RuntimeException('Use uma conta de cliente para comprar.');
        $pid=(int)post('product_id');$p=find_by_id('products',$pid);if(!$p||$p['status']!=='Ativo'||(int)$p['stock']<=0)throw new RuntimeException('Produto indisponível.');
        $cart=cart_for_user((int)$u['id']);if($cart['status']!=='Aberto')throw new RuntimeException('Seu carrinho está bloqueado por um pedido em pagamento.');
        $items=load_json('cart_items');$found=false;foreach($items as &$i){if((int)$i['cart_id']===(int)$cart['id']&&(int)$i['product_id']===$pid){$new=(int)$i['quantity']+1;if($new>(int)$p['stock'])throw new RuntimeException('Quantidade excede o estoque.');$i['quantity']=$new;$found=true;break;}}unset($i);
        if(!$found)$items[]=['id'=>next_id($items),'cart_id'=>$cart['id'],'product_id'=>$pid,'quantity'=>1];save_json('cart_items',$items);flash('success','Produto adicionado ao carrinho.');redirect('index.php?page=cart');
    }
    if ($action === 'cart_update') {
        $u=require_login();check_csrf();$cart=cart_for_user((int)$u['id']);if($cart['status']!=='Aberto')throw new RuntimeException('Carrinho bloqueado para alterações.');
        $itemId=(int)post('item_id');$qty=(int)post('quantity');$items=load_json('cart_items');$out=[];foreach($items as $i){if((int)$i['id']===$itemId&&(int)$i['cart_id']===(int)$cart['id']){if($qty<=0)continue;$p=find_by_id('products',(int)$i['product_id']);if(!$p||$qty>(int)$p['stock'])throw new RuntimeException('Quantidade indisponível.');$i['quantity']=$qty;}$out[]=$i;}save_json('cart_items',$out);flash('success','Carrinho atualizado.');redirect('index.php?page=cart');
    }
    if ($action === 'cart_coupon') {
        $u=require_login();check_csrf();$cart=cart_for_user((int)$u['id']);if($cart['status']!=='Aberto')throw new RuntimeException('Carrinho bloqueado.');$code=strtoupper(post('coupon'));
        if($code!==''){ $valid=false;foreach(load_json('promotions') as $p){if($p['mode']==='Cupom'&&strtoupper($p['code'])===$code&&promotion_active($p)){$valid=true;break;}}if(!$valid)throw new RuntimeException('Cupom inválido ou indisponível.'); }
        update_row('carts',(int)$cart['id'],fn($c)=>array_merge($c,['coupon'=>$code?:null,'updated_at'=>now_iso()]));flash('success',$code?'Cupom aplicado.':'Cupom removido.');redirect('index.php?page=cart');
    }
    if ($action === 'checkout_create') {
        $u=require_login();check_csrf();$order=create_order_from_cart((int)$u['id']);flash('success','Pedido #'.$order['id'].' criado.');redirect('index.php?page=checkout&order_id='.$order['id']);
    }
    if ($action === 'pay') {
        $u=require_login();check_csrf();process_payment((int)post('order_id'),(int)$u['id'],post('method'),post('result'));flash('success','Retorno de pagamento processado.');redirect('index.php?page=my_orders');
    }
    if ($action === 'cancel_order') {
        $u=require_login();check_csrf();cancel_order((int)post('order_id'),$u,post('reason'));flash('success','Pedido cancelado.');redirect('index.php?page='.(($u['profile']==='Administrador')?'orders':'my_orders'));
    }
    if ($action === 'order_advance') {
        $u=require_admin();check_csrf();$id=(int)post('order_id');$o=find_by_id('orders',$id);if(!$o)throw new RuntimeException('Pedido não encontrado.');$next=['Pago'=>'Em Separação','Em Separação'=>'Enviado','Enviado'=>'Entregue'][$o['status']]??null;if(!$next)throw new RuntimeException('Não há avanço manual permitido para este status.');set_order_status($id,$next,(int)$u['id']);flash('success','Pedido atualizado para '.$next.'.');redirect('index.php?page=orders');
    }

    if ($action === 'export_sales') {
        require_admin();$rows=[];foreach(load_json('orders') as $o){if(!in_array($o['status'],['Pago','Em Separação','Enviado','Entregue'],true))continue;$pay='';foreach(load_json('payments') as $p)if((int)$p['order_id']===(int)$o['id']&&$p['status']==='Aprovado')$pay=$p['method'];$rows[]=[ $o['id'],$o['created_at'],user_name((int)$o['user_id']),$o['status'],$pay,number_format((float)$o['total'],2,',','.') ];}csv_download('relatorio_vendas.csv',['Pedido','Data','Cliente','Status','Pagamento','Total'],$rows);
    }
    if ($action === 'export_stock') {
        require_admin();$rows=[];foreach(load_json('products') as $p)$rows[]=[ $p['name'],category_name((int)$p['category_id']),supplier_name($p['supplier_id']? (int)$p['supplier_id']:null),$p['size'],$p['color'],number_format((float)$p['price'],2,',','.'),$p['stock'],$p['status'] ];csv_download('relatorio_estoque.csv',['Produto','Categoria','Fornecedor','Tamanho','Cor','Preço','Estoque','Status'],$rows);
    }
    if ($action === 'export_clients') {
        require_admin();$rows=[];foreach(load_json('users') as $u){if($u['profile']!=='Cliente')continue;$count=0;$total=0.0;foreach(load_json('orders') as $o){if((int)$o['user_id']===(int)$u['id']&&in_array($o['status'],['Pago','Em Separação','Enviado','Entregue'],true)){$count++;$total+=(float)$o['total'];}}$rows[]=[ $u['name'],$u['email'],$u['created_at'],$count,number_format($total,2,',','.'),$u['status'] ];}csv_download('relatorio_clientes.csv',['Nome','E-mail','Cadastro','Pedidos válidos','Total comprado','Status'],$rows);
    }
} catch (Throwable $e) {
    flash('error',$e->getMessage());
    $back=$_SERVER['HTTP_REFERER']??'index.php';redirect($back);
}

function render_header(string $title): void {
    $u=current_user();
    if(!$u){return;}
    $admin=$u['profile']==='Administrador';
    $client=$u['profile']==='Cliente';
    $p=$_GET['page']??'home';
    $navAdmin=[
        'home'=>['⌂','Início'],'products'=>['▣','Produtos'],'categories'=>['☰','Categorias'],'orders'=>['⌁','Pedidos'],
        'promotions'=>['◇','Promoções'],'suppliers'=>['▦','Fornec.'],'users'=>['♙','Usuários'],'reports'=>['▥','Relatórios']
    ];
    $navClient=[
        'home'=>['⌂','Loja'],'cart'=>['🛒','Carrinho'],'my_orders'=>['▤','Meus pedidos'],'profile'=>['○','Minha conta']
    ];
    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' - '.APP_NAME.'</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="app"><aside class="sidebar"><div class="brand">Mary Modas</div><nav class="nav">';
    $links=$admin?$navAdmin:$navClient;
    foreach($links as $key=>$it){echo '<a class="'.($p===$key?'active':'').'" href="index.php?page='.$key.'"><span>'.$it[0].'</span><span>'.h($it[1]).'</span></a>';}
    echo '<div class="sep">Sessão</div><a href="index.php?action=logout"><span>↪</span><span>Sair</span></a></nav></aside><main class="main"><div class="topbar"><div class="topbar-title">'.h($title).'</div><div><strong>'.h($u['name']).'</strong> · '.h($u['profile']).'</div></div><div class="content">';
    foreach(take_flashes() as $f) echo '<div class="flash '.h($f['type']).'">'.h($f['message']).'</div>';
}
function render_footer(): void { if(current_user()) echo '<div class="footer-note">MVP acadêmico Mary Modas · PHP + armazenamento JSON local</div></div></main></div></body></html>'; }
function badge(string $status): string { $c=in_array($status,['Ativo','Ativa','Aprovado','Pago','Entregue'],true)?'green':(in_array($status,['Inativo','Inativa','Cancelado','Recusado','Estornado'],true)?'red':(in_array($status,['Aguardando Pagamento','Em Separação','Pendente'],true)?'orange':'blue'));return '<span class="badge '.$c.'">'.h($status).'</span>'; }
function page_title(string $p): string {return ['home'=>'Início','products'=>'Produtos','categories'=>'Categorias','suppliers'=>'Fornecedores','promotions'=>'Promoções / Cupons','users'=>'Usuários','orders'=>'Pedidos','reports'=>'Relatórios','cart'=>'Carrinho','checkout'=>'Finalizar compra','my_orders'=>'Meus pedidos','profile'=>'Minha conta'][$p]??'Mary Modas';}

if($page==='login'){
    if(current_user())redirect('index.php');
    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - '.APP_NAME.'</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="login-shell"><div class="login-card"><h1>Mary Modas</h1><p style="text-align:center;color:#666">MVP acadêmico do e-commerce</p>';
    foreach(take_flashes() as $f)echo '<div class="flash '.h($f['type']).'">'.h($f['message']).'</div>';
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="login"><div class="field"><label>E-mail</label><input class="input" name="email" type="email" required></div><div class="field"><label>Senha</label><input class="input" name="password" type="password" required></div><button class="btn btn-primary" style="width:100%">Entrar</button></form><div class="demo-box"><strong>Acessos de demonstração</strong><br>Administrador: admin@marymodas.local / admin123<br>Cliente: cliente@marymodas.local / cliente123</div><div style="text-align:center"><a href="index.php?page=register" style="color:var(--orange);font-weight:700">Criar conta de cliente</a></div></div></div></body></html>';exit;
}
if($page==='register'){
    if(current_user())redirect('index.php');
    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar conta</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="login-shell"><div class="login-card" style="width:min(680px,100%)"><h1>Criar conta</h1>';
    foreach(take_flashes() as $f)echo '<div class="flash '.h($f['type']).'">'.h($f['message']).'</div>';
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="register"><div class="form-row"><div class="field"><label>Nome *</label><input class="input" name="name" required></div><div class="field"><label>E-mail *</label><input class="input" name="email" type="email" required></div><div class="field"><label>Telefone</label><input class="input" name="phone"></div><div class="field"><label>CEP *</label><input class="input" name="cep" required></div><div class="field"><label>Rua/Avenida *</label><input class="input" name="street" required></div><div class="field"><label>Número *</label><input class="input" name="number" required></div><div class="field"><label>Senha *</label><input class="input" name="password" type="password" minlength="8" required></div><div class="field"><label>Confirmar senha *</label><input class="input" name="password_confirm" type="password" minlength="8" required></div></div><div class="form-actions"><a class="btn btn-secondary" href="index.php?page=login">Cancelar</a><button class="btn btn-primary">Cadastrar</button></div></form></div></div></body></html>';exit;
}

$u=require_login();
render_header(page_title($page));

if($page==='home'){
    if($u['profile']==='Administrador'){
        $orders=load_json('orders');$sales=array_filter($orders,fn($o)=>in_array($o['status'],['Pago','Em Separação','Enviado','Entregue'],true));$revenue=array_sum(array_map(fn($o)=>(float)$o['total'],$sales));$low=count(array_filter(load_json('products'),fn($p)=>(int)$p['stock']<=10));
        echo '<div class="hero"><h1>Painel administrativo</h1><p>Visão geral do MVP Mary Modas. Use o menu lateral para testar os CRUDs, pedidos, promoções e relatórios.</p></div><div class="grid grid-4"><div class="stat"><div class="label">Produtos cadastrados</div><div class="num">'.count(load_json('products')).'</div></div><div class="stat"><div class="label">Vendas realizadas</div><div class="num">'.count($sales).'</div></div><div class="stat"><div class="label">Faturamento</div><div class="num">'.money($revenue).'</div></div><div class="stat"><div class="label">Estoque baixo (≤10)</div><div class="num">'.$low.'</div></div></div><div class="panel"><h2>Atalhos</h2><div class="actions"><a class="btn btn-primary" href="index.php?page=products&edit=new">Novo produto</a><a class="btn btn-outline" href="index.php?page=promotions&edit=new">Nova promoção</a><a class="btn btn-outline" href="index.php?page=reports">Abrir relatórios</a><form method="post" onsubmit="return confirm(\'Restaurar todos os dados de demonstração?\')">'.csrf_field().'<input type="hidden" name="action" value="reset_demo"><button class="btn btn-secondary">Restaurar demo</button></form></div></div>';
    }else{
        $q=strtolower(getq('q'));$size=getq('size');$color=getq('color');$products=array_filter(load_json('products'),function($p)use($q,$size,$color){if($p['status']!=='Ativo'||(int)$p['stock']<=0)return false;if($q!==''&&!str_contains(strtolower($p['name']),$q))return false;if($size!==''&&$p['size']!==$size)return false;if($color!==''&&$p['color']!==$color)return false;return true;});$sizes=array_values(array_unique(array_column(load_json('products'),'size')));$colors=array_values(array_unique(array_column(load_json('products'),'color')));
        echo '<div class="hero"><h1>Mary Modas</h1><p>Moda, estilo e praticidade. Este catálogo já usa tamanho, cor, estoque e promoções do MVP.</p></div><form class="panel toolbar"><div><label>Pesquisar</label><input class="input" name="q" value="'.h(getq('q')).'" placeholder="Nome do produto"></div><div><label>Tamanho</label><select name="size"><option value="">Todos</option>';foreach($sizes as $s)echo '<option '.($size===$s?'selected':'').'>'.h($s).'</option>';echo '</select></div><div><label>Cor</label><select name="color"><option value="">Todas</option>';foreach($colors as $c)echo '<option '.($color===$c?'selected':'').'>'.h($c).'</option>';echo '</select></div><input type="hidden" name="page" value="home"><button class="btn btn-primary">Filtrar</button></form><div class="product-grid">';
        foreach($products as $p){$automatic=0.0;foreach(load_json('promotions') as $pr){if($pr['mode']==='Automática')$automatic=max($automatic,promotion_discount_for_product($pr,$p,1));}$promoPrice=(float)$p['price']-$automatic;echo '<div class="product-card"><div class="product-img">👗</div><div class="product-body"><div class="product-title">'.h($p['name']).'</div><div class="chips"><span class="chip">'.h($p['size']).'</span><span class="chip">'.h($p['color']).'</span><span class="chip">'.h(category_name((int)$p['category_id'])).'</span></div>';if($automatic>0)echo '<div><span class="old-price">'.money($p['price']).'</span> <span class="badge orange">Promoção</span></div>';echo '<div class="price">'.money($promoPrice).'</div><div class="help">Estoque: '.(int)$p['stock'].'</div><form method="post" style="margin-top:auto">'.csrf_field().'<input type="hidden" name="action" value="cart_add"><input type="hidden" name="product_id" value="'.$p['id'].'"><button class="btn btn-primary" style="width:100%">Adicionar ao carrinho</button></form></div></div>';}
        if(!$products)echo '<div class="panel empty">Nenhum produto encontrado.</div>';echo '</div>';
    }
}

if($page==='products'){
    require_admin();$edit=$_GET['edit']??null;$p=$edit&&$edit!=='new'?find_by_id('products',(int)$edit):null;
    if($edit){echo '<div class="panel"><h2>'.($p?'Editar Produto':'Cadastrar Produto').'</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="product_save"><input type="hidden" name="id" value="'.h($p['id']??'').'">';echo '<div class="form-row"><div class="field"><label>Nome do Produto *</label><input class="input" name="name" value="'.h($p['name']??'').'" required></div><div class="field"><label>Preço (R$) *</label><input class="input" name="price" value="'.h($p['price']??'').'" required></div><div class="field"><label>Descrição</label><textarea name="description">'.h($p['description']??'').'</textarea></div><div class="field"><label>Categoria *</label><select name="category_id" required><option value="">Selecione</option>';foreach(load_json('categories') as $c)if($c['status']==='Ativo')echo '<option value="'.$c['id'].'" '.(((int)($p['category_id']??0)===(int)$c['id'])?'selected':'').'>'.h($c['name']).'</option>';echo '</select></div><div class="field"><label>Fornecedor</label><select name="supplier_id"><option value="">Sem fornecedor</option>';foreach(load_json('suppliers') as $s)if($s['status']==='Ativo')echo '<option value="'.$s['id'].'" '.(((int)($p['supplier_id']??0)===(int)$s['id'])?'selected':'').'>'.h($s['name']).'</option>';echo '</select></div><div class="field"><label>Tamanho *</label><input class="input" name="size" value="'.h($p['size']??'M').'" required></div><div class="field"><label>Cor *</label><input class="input" name="color" value="'.h($p['color']??'Preto').'" required></div><div class="field"><label>Estoque *</label><input class="input" name="stock" type="number" min="0" value="'.h($p['stock']??'0').'" required></div><div class="field"><label>Imagem (URL opcional)</label><input class="input" name="image" value="'.h($p['image']??'').'" placeholder="Deixe vazio para imagem padrão"></div></div><div class="form-actions"><a class="btn btn-secondary" href="index.php?page=products">Cancelar</a><button class="btn btn-primary">Salvar alterações</button></div></form></div>';}
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Produtos</h2><a class="btn btn-primary" href="index.php?page=products&edit=new">Novo Produto</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Produto</th><th>Categoria</th><th>Tam.</th><th>Cor</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th></tr></thead><tbody>';foreach(load_json('products') as $r){echo '<tr><td><strong>'.h($r['name']).'</strong><br><span class="help">'.h($r['description']).'</span></td><td>'.h(category_name((int)$r['category_id'])).'</td><td>'.h($r['size']).'</td><td>'.h($r['color']).'</td><td>'.money($r['price']).'</td><td class="'.((int)$r['stock']<=10?'low-stock':'').'">'.$r['stock'].'</td><td>'.badge($r['status']).'</td><td><div class="actions"><a class="btn btn-sm btn-outline" href="index.php?page=products&edit='.$r['id'].'">Editar</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="product_toggle"><input type="hidden" name="id" value="'.$r['id'].'"><button class="btn btn-sm '.($r['status']==='Ativo'?'btn-danger':'btn-primary').'">'.($r['status']==='Ativo'?'Inativar':'Ativar').'</button></form></div></td></tr>'; }echo '</tbody></table></div></div>';
}

if($page==='categories'){
    require_admin();$edit=$_GET['edit']??null;$r=$edit&&$edit!=='new'?find_by_id('categories',(int)$edit):null;if($edit){echo '<div class="panel"><h2>'.($r?'Editar Categoria':'Cadastrar Categoria').'</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="'.h($r['id']??'').'"><div class="field"><label>Nome da Categoria *</label><input class="input" name="name" value="'.h($r['name']??'').'" required></div><div class="field"><label>Descrição</label><textarea name="description">'.h($r['description']??'').'</textarea></div><div class="form-actions"><a class="btn btn-secondary" href="index.php?page=categories">Cancelar</a><button class="btn btn-primary">Salvar alterações</button></div></form></div>';}
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Categorias</h2><a class="btn btn-primary" href="index.php?page=categories&edit=new">Nova Categoria</a></div><table class="table"><thead><tr><th>Nome</th><th>Descrição</th><th>Status</th><th>Ações</th></tr></thead><tbody>';foreach(load_json('categories') as $c){echo '<tr><td><strong>'.h($c['name']).'</strong></td><td>'.h($c['description']).'</td><td>'.badge($c['status']).'</td><td><div class="actions"><a class="btn btn-sm btn-outline" href="index.php?page=categories&edit='.$c['id'].'">Editar</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="category_toggle"><input type="hidden" name="id" value="'.$c['id'].'"><button class="btn btn-sm '.($c['status']==='Ativo'?'btn-danger':'btn-primary').'">'.($c['status']==='Ativo'?'Inativar':'Ativar').'</button></form></div></td></tr>';}echo '</tbody></table></div>';
}

if($page==='suppliers'){
    require_admin();$edit=$_GET['edit']??null;$r=$edit&&$edit!=='new'?find_by_id('suppliers',(int)$edit):null;if($edit){echo '<div class="panel"><h2>'.($r?'Editar Fornecedor':'Cadastrar Fornecedor').'</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="supplier_save"><input type="hidden" name="id" value="'.h($r['id']??'').'"><div class="form-row"><div class="field"><label>Nome / Razão Social *</label><input class="input" name="name" value="'.h($r['name']??'').'" required></div><div class="field"><label>CPF / CNPJ</label><input class="input" name="document" value="'.h($r['document']??'').'"></div><div class="field"><label>Telefone</label><input class="input" name="phone" value="'.h($r['phone']??'').'"></div><div class="field"><label>E-mail</label><input class="input" name="email" type="email" value="'.h($r['email']??'').'"></div></div><div class="form-actions"><a class="btn btn-secondary" href="index.php?page=suppliers">Cancelar</a><button class="btn btn-primary">Salvar alterações</button></div></form></div>';}
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Fornecedores</h2><a class="btn btn-primary" href="index.php?page=suppliers&edit=new">Novo Fornecedor</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Nome</th><th>Documento</th><th>Contato</th><th>Status</th><th>Ações</th></tr></thead><tbody>';foreach(load_json('suppliers') as $s){echo '<tr><td><strong>'.h($s['name']).'</strong></td><td>'.h($s['document']).'</td><td>'.h($s['phone']).'<br>'.h($s['email']).'</td><td>'.badge($s['status']).'</td><td><div class="actions"><a class="btn btn-sm btn-outline" href="index.php?page=suppliers&edit='.$s['id'].'">Editar</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="supplier_toggle"><input type="hidden" name="id" value="'.$s['id'].'"><button class="btn btn-sm '.($s['status']==='Ativo'?'btn-danger':'btn-primary').'">'.($s['status']==='Ativo'?'Inativar':'Ativar').'</button></form></div></td></tr>';}echo '</tbody></table></div></div>';
}

if($page==='promotions'){
    require_admin();
    $edit=$_GET['edit']??null;
    $r=$edit&&$edit!=='new'?find_by_id('promotions',(int)$edit):null;
    if($edit){
        echo '<div class="panel"><h2>'.($r?'Editar Promoção':'Cadastrar Promoção / Cupom').'</h2><form method="post">'.csrf_field();
        echo '<input type="hidden" name="action" value="promotion_save"><input type="hidden" name="id" value="'.h($r['id']??'').'">';
        echo '<div class="form-row">';
        echo '<div class="field"><label>Nome *</label><input class="input" name="name" value="'.h($r['name']??'').'" required></div>';
        echo '<div class="field"><label>Tipo de desconto *</label><select name="discount_type"><option '.(($r['discount_type']??'')==='Percentual'?'selected':'').'>Percentual</option><option '.(($r['discount_type']??'')==='Valor'?'selected':'').'>Valor</option></select></div>';
        echo '<div class="field"><label>Modo *</label><select name="mode"><option '.(($r['mode']??'')==='Automática'?'selected':'').'>Automática</option><option '.(($r['mode']??'')==='Cupom'?'selected':'').'>Cupom</option></select></div>';
        $readonly=($r['used']??false)?'readonly':'';
        echo '<div class="field"><label>Código</label><input class="input" name="code" value="'.h($r['code']??'').'" '.$readonly.'></div>';
        echo '<div class="field"><label>Desconto *</label><input class="input" name="discount_value" value="'.h($r['discount_value']??'10').'" required></div>';
        echo '<div class="field"><label>Aplicação *</label><select name="application"><option '.(($r['application']??'')==='Geral'?'selected':'').'>Geral</option><option '.(($r['application']??'')==='Produto'?'selected':'').'>Produto</option><option '.(($r['application']??'')==='Categoria'?'selected':'').'>Categoria</option></select></div>';
        echo '<div class="field"><label>Produto alvo (se aplicável)</label><select name="product_target"><option value="">Selecione</option>';
        foreach(load_json('products') as $p){
            $sel=((int)($r['target_id']??0)===(int)$p['id']&&($r['application']??'')==='Produto')?'selected':'';
            echo '<option value="'.$p['id'].'" '.$sel.'>'.h($p['name'].' / '.$p['size'].' / '.$p['color']).'</option>';
        }
        echo '</select></div>';
        echo '<div class="field"><label>Categoria alvo (se aplicável)</label><select name="category_target"><option value="">Selecione</option>';
        foreach(load_json('categories') as $c){
            $sel=((int)($r['target_id']??0)===(int)$c['id']&&($r['application']??'')==='Categoria')?'selected':'';
            echo '<option value="'.$c['id'].'" '.$sel.'>'.h($c['name']).'</option>';
        }
        echo '</select></div>';
        echo '<div class="field"><label>Data inicial *</label><input class="input" type="date" name="start_date" value="'.h($r['start_date']??date('Y-m-d')).'" required></div>';
        echo '<div class="field"><label>Data final *</label><input class="input" type="date" name="end_date" value="'.h($r['end_date']??date('Y-m-d',strtotime('+30 days'))).'" required></div>';
        echo '</div><div class="form-actions"><a class="btn btn-secondary" href="index.php?page=promotions">Cancelar</a><button class="btn btn-primary">Salvar alterações</button></div></form></div>';
    }
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Promoções / Cupons</h2><a class="btn btn-primary" href="index.php?page=promotions&edit=new">Nova Promoção</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Nome</th><th>Modo</th><th>Desconto</th><th>Aplicação</th><th>Período</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
    foreach(load_json('promotions') as $pr){
        $d=$pr['discount_type']==='Percentual'?$pr['discount_value'].'%':money($pr['discount_value']);
        echo '<tr><td><strong>'.h($pr['name']).'</strong><br><span class="help">'.h($pr['code']).'</span></td><td>'.h($pr['mode']).'</td><td>'.$d.'</td><td>'.h($pr['application']).'</td><td>'.h($pr['start_date']).' a '.h($pr['end_date']).'</td><td>'.badge($pr['status']).'</td><td><div class="actions"><a class="btn btn-sm btn-outline" href="index.php?page=promotions&edit='.$pr['id'].'">Editar</a><form method="post">'.csrf_field().'<input type="hidden" name="action" value="promotion_toggle"><input type="hidden" name="id" value="'.$pr['id'].'"><button class="btn btn-sm '.($pr['status']==='Ativa'?'btn-danger':'btn-primary').'">'.($pr['status']==='Ativa'?'Inativar':'Ativar').'</button></form></div></td></tr>';
    }
    echo '</tbody></table></div></div>';
}

if($page==='users'){
    require_admin();echo '<div class="panel"><h2>Usuários</h2><div class="table-wrap"><table class="table"><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Endereço</th><th>Status</th><th>Ações</th></tr></thead><tbody>';foreach(load_json('users') as $x){echo '<tr><td><strong>'.h($x['name']).'</strong></td><td>'.h($x['email']).'</td><td><form method="post" class="actions">'.csrf_field().'<input type="hidden" name="action" value="admin_user_update"><input type="hidden" name="id" value="'.$x['id'].'"><select name="profile" '.((int)$x['id']===(int)$u['id']?'disabled':'').'><option '.($x['profile']==='Cliente'?'selected':'').'>Cliente</option><option '.($x['profile']==='Administrador'?'selected':'').'>Administrador</option></select>';if((int)$x['id']!==(int)$u['id'])echo '<button class="btn btn-sm btn-outline">Salvar perfil</button>';echo '</form></td><td>'.h($x['street'].', '.$x['number'].' - '.$x['cep']).'</td><td>'.badge($x['status']).'</td><td>';if((int)$x['id']!==(int)$u['id'])echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="admin_user_toggle"><input type="hidden" name="id" value="'.$x['id'].'"><button class="btn btn-sm '.($x['status']==='Ativo'?'btn-danger':'btn-primary').'">'.($x['status']==='Ativo'?'Desativar':'Ativar').'</button></form>';else echo '<span class="help">Conta atual</span>';echo '</td></tr>';}echo '</tbody></table></div></div>';
}

if($page==='cart'){
    if($u['profile']!=='Cliente')redirect('index.php');$cart=cart_for_user((int)$u['id']);$t=cart_totals($cart);echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Seu carrinho</h2>'.badge($cart['status']).'</div>';
    if(!$t['rows'])echo '<div class="empty">Seu carrinho está vazio.</div>';else{echo '<div class="table-wrap"><table class="table"><thead><tr><th>Produto</th><th>Tamanho/Cor</th><th>Preço</th><th>Quantidade</th><th>Subtotal</th></tr></thead><tbody>';foreach($t['rows'] as $r){$i=$r['item'];$p=$r['product'];echo '<tr><td><strong>'.h($p['name']).'</strong></td><td>'.h($p['size'].' / '.$p['color']).'</td><td>'.money($p['price']).'</td><td>';if($cart['status']==='Aberto')echo '<form method="post" class="actions">'.csrf_field().'<input type="hidden" name="action" value="cart_update"><input type="hidden" name="item_id" value="'.$i['id'].'"><input class="input" style="width:80px" type="number" min="0" max="'.$p['stock'].'" name="quantity" value="'.$i['quantity'].'"><button class="btn btn-sm btn-outline">Atualizar</button></form>';else echo $i['quantity'];echo '</td><td>'.money($r['line_subtotal']).'</td></tr>'; }echo '</tbody></table></div><div class="grid grid-2" style="margin-top:18px"><div>';if($cart['status']==='Aberto')echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="cart_coupon"><div class="field"><label>Cupom</label><div class="actions"><input class="input" style="max-width:260px" name="coupon" value="'.h($cart['coupon']??'').'" placeholder="Ex.: MARY20"><button class="btn btn-outline">Aplicar</button></div></div></form>';echo '<div class="help">Promoções automáticas e cupons não acumulam: o sistema usa o maior desconto válido.</div></div><div class="panel" style="margin:0"><div class="actions"><span>Subtotal</span><strong class="right">'.money($t['subtotal']).'</strong></div><div class="actions"><span>Desconto</span><strong class="right">- '.money($t['discount']).'</strong></div><hr><div class="actions"><span>Total</span><strong class="right" style="font-size:22px;color:var(--orange)">'.money($t['total']).'</strong></div></div></div>';if($cart['status']==='Aberto')echo '<form method="post" style="text-align:right;margin-top:18px">'.csrf_field().'<input type="hidden" name="action" value="checkout_create"><button class="btn btn-primary">Finalizar compra</button></form>';else echo '<div class="flash warning" style="margin-top:18px">Carrinho bloqueado por um pedido em pagamento.</div>';}
    echo '</div>';
}

if($page==='checkout'){
    if($u['profile']!=='Cliente')redirect('index.php');$id=(int)getq('order_id');$o=find_by_id('orders',$id);if(!$o||(int)$o['user_id']!==(int)$u['id']){echo '<div class="panel empty">Pedido não encontrado.</div>';}else{$items=array_values(array_filter(load_json('order_items'),fn($i)=>(int)$i['order_id']===$id));echo '<div class="stepper"><div class="step done">Carrinho</div><div class="step done">Pedido criado</div><div class="step active">Pagamento</div><div class="step">Concluído</div></div><div class="grid grid-2"><div class="panel"><h3>Pedido #'.$o['id'].' — '.h($o['status']).'</h3>';foreach($items as $i)echo '<div class="actions" style="margin:8px 0"><span>'.h($i['product_name'].' '.$i['size'].'/'.$i['color'].' x'.$i['quantity']).'</span><strong class="right">'.money($i['subtotal']).'</strong></div>';echo '<hr><div class="actions"><span>Subtotal</span><strong class="right">'.money($o['subtotal']).'</strong></div><div class="actions"><span>Desconto</span><strong class="right">- '.money($o['discount']).'</strong></div><div class="actions"><span>Total</span><strong class="right" style="color:var(--orange);font-size:20px">'.money($o['total']).'</strong></div><div class="panel" style="margin-top:15px"><strong>Entrega:</strong> '.h($o['street'].', '.$o['number']).'<br><span class="help">CEP '.h($o['cep']).' · endereço congelado neste pedido</span></div></div><div class="panel"><h3>Forma de pagamento</h3><form method="post">'.csrf_field().'<input type="hidden" name="action" value="pay"><input type="hidden" name="order_id" value="'.$o['id'].'"><label class="payment-option"><input type="radio" name="method" value="PIX" checked> PIX</label><label class="payment-option"><input type="radio" name="method" value="Cartão de Crédito"> Cartão de Crédito</label><label class="payment-option"><input type="radio" name="method" value="Boleto Bancário"> Boleto Bancário</label><div class="help" style="margin:12px 0">MVP: escolha o retorno que deseja simular.</div><div class="actions"><button class="btn btn-primary" name="result" value="approve">Simular aprovado</button><button class="btn btn-outline" name="result" value="pending">Simular pendente</button><button class="btn btn-danger" name="result" value="reject">Simular recusado</button></div></form></div></div>';}
}

if($page==='my_orders'){
    if($u['profile']!=='Cliente')redirect('index.php');$orders=array_values(array_filter(load_json('orders'),fn($o)=>(int)$o['user_id']===(int)$u['id']));usort($orders,fn($a,$b)=>$b['id']<=>$a['id']);echo '<div class="panel"><h2>Meus pedidos</h2>';if(!$orders)echo '<div class="empty">Nenhum pedido criado ainda.</div>';foreach($orders as $o){$items=array_values(array_filter(load_json('order_items'),fn($i)=>(int)$i['order_id']===(int)$o['id']));echo '<div class="panel"><div class="actions"><strong>Pedido #'.$o['id'].'</strong>'.badge($o['status']).'<span class="right">'.money($o['total']).'</span></div><div class="help">'.h($o['created_at']).' · '.h($o['street'].', '.$o['number']).'</div><div style="margin:10px 0">';foreach($items as $i)echo '<div>'.h($i['product_name'].' · '.$i['size'].'/'.$i['color'].' · '.$i['quantity'].' un.').'</div>';echo '</div><div class="actions">';if($o['status']==='Aguardando Pagamento')echo '<a class="btn btn-sm btn-primary" href="index.php?page=checkout&order_id='.$o['id'].'">Pagar / tentar novamente</a>';if($o['status']==='Aguardando Pagamento')echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="'.$o['id'].'"><button class="btn btn-sm btn-danger">Cancelar</button></form>';echo '</div></div>'; }echo '</div>';
}

if($page==='profile'){
    echo '<div class="panel"><h2>Editar perfil</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="profile_save"><div class="form-row"><div class="field"><label>Nome *</label><input class="input" name="name" value="'.h($u['name']).'" required></div><div class="field"><label>E-mail *</label><input class="input" name="email" type="email" value="'.h($u['email']).'" required></div><div class="field"><label>Telefone</label><input class="input" name="phone" value="'.h($u['phone']).'"></div><div class="field"><label>CEP *</label><input class="input" name="cep" value="'.h($u['cep']).'" required></div><div class="field"><label>Rua/Avenida *</label><input class="input" name="street" value="'.h($u['street']).'" required></div><div class="field"><label>Número *</label><input class="input" name="number" value="'.h($u['number']).'" required></div><div class="field"><label>Nova senha</label><input class="input" name="new_password" type="password" minlength="8" placeholder="Deixe em branco para manter"></div></div><div class="form-actions"><button class="btn btn-primary">Salvar alterações</button></div></form></div>';
}

if($page==='orders'){
    require_admin();$orders=load_json('orders');usort($orders,fn($a,$b)=>$b['id']<=>$a['id']);echo '<div class="panel"><h2>Pedidos</h2><div class="table-wrap"><table class="table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead><tbody>';foreach($orders as $o){echo '<tr><td>#'.$o['id'].'</td><td>'.h(user_name((int)$o['user_id'])).'</td><td>'.h($o['created_at']).'</td><td>'.money($o['total']).'</td><td>'.badge($o['status']).'</td><td><div class="actions">';if(in_array($o['status'],['Pago','Em Separação','Enviado'],true))echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="order_advance"><input type="hidden" name="order_id" value="'.$o['id'].'"><button class="btn btn-sm btn-primary">Avançar status</button></form>';if(in_array($o['status'],['Aguardando Pagamento','Pago','Em Separação'],true))echo '<form method="post" onsubmit="return confirm(\'Cancelar este pedido?\')">'.csrf_field().'<input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="'.$o['id'].'"><button class="btn btn-sm btn-danger">Cancelar</button></form>';echo '</div></td></tr>'; }echo '</tbody></table></div></div>';
}

if($page==='reports'){
    require_admin();$orders=load_json('orders');$valid=array_values(array_filter($orders,fn($o)=>in_array($o['status'],['Pago','Em Separação','Enviado','Entregue'],true)));$revenue=array_sum(array_map(fn($o)=>(float)$o['total'],$valid));$low=array_values(array_filter(load_json('products'),fn($p)=>(int)$p['stock']<=10));$clients=array_values(array_filter(load_json('users'),fn($x)=>$x['profile']==='Cliente'));
    echo '<div class="grid grid-3"><div class="stat"><div class="label">Vendas realizadas</div><div class="num">'.count($valid).'</div><div>'.money($revenue).'</div></div><div class="stat"><div class="label">Produtos com estoque baixo</div><div class="num">'.count($low).'</div><div>10 unidades ou menos</div></div><div class="stat"><div class="label">Clientes cadastrados</div><div class="num">'.count($clients).'</div></div></div>';
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Relatório de Vendas</h2><a class="btn btn-sm btn-outline" href="index.php?action=export_sales">Exportar CSV</a></div><table class="table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th></tr></thead><tbody>';foreach($valid as $o)echo '<tr><td>#'.$o['id'].'</td><td>'.h(user_name((int)$o['user_id'])).'</td><td>'.badge($o['status']).'</td><td>'.money($o['total']).'</td></tr>';echo '</tbody></table></div>';
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Produtos / Estoque</h2><a class="btn btn-sm btn-outline" href="index.php?action=export_stock">Exportar CSV</a></div><table class="table"><thead><tr><th>Produto</th><th>Tamanho/Cor</th><th>Estoque</th><th>Status</th></tr></thead><tbody>';foreach(load_json('products') as $p)echo '<tr><td>'.h($p['name']).'</td><td>'.h($p['size'].' / '.$p['color']).'</td><td class="'.((int)$p['stock']<=10?'low-stock':'').'">'.$p['stock'].'</td><td>'.badge($p['status']).'</td></tr>';echo '</tbody></table></div>';
    echo '<div class="panel"><div class="actions"><h2 style="margin-right:auto">Clientes</h2><a class="btn btn-sm btn-outline" href="index.php?action=export_clients">Exportar CSV</a></div><table class="table"><thead><tr><th>Cliente</th><th>Pedidos válidos</th><th>Total comprado</th><th>Status</th></tr></thead><tbody>';foreach($clients as $c){$count=0;$total=0.0;foreach($valid as $o)if((int)$o['user_id']===(int)$c['id']){$count++;$total+=(float)$o['total'];}echo '<tr><td>'.h($c['name']).'<br><span class="help">'.h($c['email']).'</span></td><td>'.$count.'</td><td>'.money($total).'</td><td>'.badge($c['status']).'</td></tr>';}echo '</tbody></table></div>';
}

render_footer();
