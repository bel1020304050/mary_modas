<?php

declare(strict_types=1);

const APP_NAME = 'Mary Modas MVP';
const DATA_DIR = __DIR__ . '/data';

function ensure_data_dir(): void {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
}

function data_file(string $name): string {
    ensure_data_dir();
    return DATA_DIR . '/' . $name . '.json';
}

function load_json(string $name, array $default = []): array {
    $file = data_file($name);
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $default;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function save_json(string $name, array $data): void {
    $file = data_file($name);
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmp, $file);
}

function next_id(array $rows): int {
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['id'] ?? 0));
    }
    return $max + 1;
}

function now_iso(): string {
    return date('Y-m-d H:i:s');
}

function money(float|int|string $value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function h(string|int|float|null $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    foreach (load_json('users') as $u) {
        if ((int)$u['id'] === (int)$_SESSION['user_id']) return $u;
    }
    return null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        flash('error', 'Faça login para continuar.');
        redirect('index.php?page=login');
    }
    if (($u['status'] ?? '') !== 'Ativo') {
        session_destroy();
        session_start();
        flash('error', 'Esta conta está inativa.');
        redirect('index.php?page=login');
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if (($u['profile'] ?? '') !== 'Administrador') {
        flash('error', 'Acesso restrito ao administrador.');
        redirect('index.php');
    }
    return $u;
}

function find_by_id(string $name, int $id): ?array {
    foreach (load_json($name) as $row) {
        if ((int)($row['id'] ?? 0) === $id) return $row;
    }
    return null;
}

function update_row(string $name, int $id, callable $fn): ?array {
    $rows = load_json($name);
    $updated = null;
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) === $id) {
            $row = $fn($row);
            $updated = $row;
            break;
        }
    }
    unset($row);
    save_json($name, $rows);
    return $updated;
}

function category_name(int $id): string {
    $row = find_by_id('categories', $id);
    return $row['name'] ?? 'Sem categoria';
}

function supplier_name(?int $id): string {
    if (!$id) return '—';
    $row = find_by_id('suppliers', $id);
    return $row['name'] ?? '—';
}

function user_name(int $id): string {
    $row = find_by_id('users', $id);
    return $row['name'] ?? 'Usuário';
}

function order_statuses(): array {
    return ['Aguardando Pagamento', 'Pago', 'Em Separação', 'Enviado', 'Entregue', 'Cancelado'];
}

function payment_statuses(): array {
    return ['Pendente', 'Aprovado', 'Recusado', 'Estornado'];
}

function cart_for_user(int $userId): array {
    $carts = load_json('carts');
    foreach ($carts as $c) {
        if ((int)$c['user_id'] === $userId && in_array($c['status'], ['Aberto','Em Pagamento'], true)) return $c;
    }
    $cart = [
        'id' => next_id($carts),
        'user_id' => $userId,
        'status' => 'Aberto',
        'coupon' => null,
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
    ];
    $carts[] = $cart;
    save_json('carts', $carts);
    return $cart;
}

function cart_items(int $cartId): array {
    return array_values(array_filter(load_json('cart_items'), fn($i) => (int)$i['cart_id'] === $cartId));
}

function promotion_active(array $p): bool {
    if (($p['status'] ?? '') !== 'Ativa') return false;
    $today = date('Y-m-d');
    return $today >= ($p['start_date'] ?? '0000-00-00') && $today <= ($p['end_date'] ?? '9999-12-31');
}

function promotion_discount_for_product(array $promotion, array $product, int $qty): float {
    if (!promotion_active($promotion)) return 0.0;
    $eligible = false;
    $scope = $promotion['application'] ?? 'Geral';
    if ($scope === 'Geral') $eligible = true;
    if ($scope === 'Produto' && (int)($promotion['target_id'] ?? 0) === (int)$product['id']) $eligible = true;
    if ($scope === 'Categoria' && (int)($promotion['target_id'] ?? 0) === (int)$product['category_id']) $eligible = true;
    if (!$eligible) return 0.0;
    $subtotal = (float)$product['price'] * $qty;
    if (($promotion['discount_type'] ?? 'Percentual') === 'Percentual') {
        $pct = min(50, max(1, (float)$promotion['discount_value']));
        return $subtotal * ($pct / 100);
    }
    return min($subtotal, max(0, (float)$promotion['discount_value']));
}

function cart_totals(array $cart): array {
    $items = cart_items((int)$cart['id']);
    $products = load_json('products');
    $promotions = load_json('promotions');
    $productMap = [];
    foreach ($products as $p) $productMap[(int)$p['id']] = $p;
    $subtotal = 0.0;
    $rows = [];
    foreach ($items as $item) {
        $p = $productMap[(int)$item['product_id']] ?? null;
        if (!$p) continue;
        $line = (float)$p['price'] * (int)$item['quantity'];
        $subtotal += $line;
        $rows[] = ['item'=>$item, 'product'=>$p, 'line_subtotal'=>$line];
    }

    $bestDiscount = 0.0;
    $bestPromotion = null;
    foreach ($promotions as $promo) {
        if (!promotion_active($promo)) continue;
        if (($promo['mode'] ?? '') === 'Cupom') {
            $code = strtoupper(trim((string)($cart['coupon'] ?? '')));
            if ($code === '' || strtoupper((string)($promo['code'] ?? '')) !== $code) continue;
        }
        $discount = 0.0;
        foreach ($rows as $r) {
            $discount += promotion_discount_for_product($promo, $r['product'], (int)$r['item']['quantity']);
        }
        if ($discount > $bestDiscount) {
            $bestDiscount = $discount;
            $bestPromotion = $promo;
        }
    }
    $bestDiscount = min($subtotal, $bestDiscount);
    return [
        'rows'=>$rows,
        'subtotal'=>$subtotal,
        'discount'=>$bestDiscount,
        'total'=>max(0, $subtotal - $bestDiscount),
        'promotion'=>$bestPromotion,
    ];
}

function create_order_from_cart(int $userId): array {
    $cart = cart_for_user($userId);
    if ($cart['status'] === 'Em Pagamento') {
        foreach (load_json('orders') as $o) {
            if ((int)($o['cart_id'] ?? 0) === (int)$cart['id'] && $o['status'] === 'Aguardando Pagamento') return $o;
        }
    }
    $totals = cart_totals($cart);
    if (!$totals['rows']) throw new RuntimeException('Carrinho vazio.');
    foreach ($totals['rows'] as $r) {
        $p = $r['product'];
        if (($p['status'] ?? '') !== 'Ativo') throw new RuntimeException('Há produto inativo no carrinho.');
        if ((int)$r['item']['quantity'] > (int)$p['stock']) throw new RuntimeException('Estoque insuficiente para ' . $p['name'] . '.');
    }
    $user = find_by_id('users', $userId);
    if (!$user) throw new RuntimeException('Usuário não encontrado.');

    $orders = load_json('orders');
    $order = [
        'id' => next_id($orders),
        'cart_id' => (int)$cart['id'],
        'user_id' => $userId,
        'status' => 'Aguardando Pagamento',
        'cep' => $user['cep'],
        'street' => $user['street'],
        'number' => $user['number'],
        'subtotal' => round($totals['subtotal'], 2),
        'discount' => round($totals['discount'], 2),
        'total' => round($totals['total'], 2),
        'promotion_id' => $totals['promotion']['id'] ?? null,
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
        'cancel_reason' => null,
    ];
    $orders[] = $order;
    save_json('orders', $orders);

    $orderItems = load_json('order_items');
    foreach ($totals['rows'] as $r) {
        $p = $r['product'];
        $q = (int)$r['item']['quantity'];
        $orderItems[] = [
            'id' => next_id($orderItems),
            'order_id' => $order['id'],
            'product_id' => $p['id'],
            'product_name' => $p['name'],
            'size' => $p['size'],
            'color' => $p['color'],
            'unit_price' => (float)$p['price'],
            'quantity' => $q,
            'subtotal' => round((float)$p['price'] * $q, 2),
        ];
    }
    save_json('order_items', $orderItems);

    update_row('carts', (int)$cart['id'], function(array $c): array {
        $c['status'] = 'Em Pagamento';
        $c['updated_at'] = now_iso();
        return $c;
    });
    add_order_history($order['id'], $userId, null, 'Aguardando Pagamento');
    return $order;
}

function add_order_history(int $orderId, int $userId, ?string $from, string $to): void {
    $rows = load_json('order_history');
    $rows[] = [
        'id'=>next_id($rows),
        'order_id'=>$orderId,
        'user_id'=>$userId,
        'from'=>$from,
        'to'=>$to,
        'created_at'=>now_iso(),
    ];
    save_json('order_history', $rows);
}

function set_order_status(int $orderId, string $newStatus, int $responsibleUserId): void {
    $order = find_by_id('orders', $orderId);
    if (!$order) throw new RuntimeException('Pedido não encontrado.');
    $old = $order['status'];
    update_row('orders', $orderId, function(array $o) use ($newStatus): array {
        $o['status'] = $newStatus;
        $o['updated_at'] = now_iso();
        return $o;
    });
    add_order_history($orderId, $responsibleUserId, $old, $newStatus);
}

function process_payment(int $orderId, int $userId, string $method, string $result): void {
    $order = find_by_id('orders', $orderId);
    if (!$order || (int)$order['user_id'] !== $userId) throw new RuntimeException('Pedido inválido.');
    if ($order['status'] !== 'Aguardando Pagamento') throw new RuntimeException('Este pedido não está aguardando pagamento.');

    $allowedMethods = ['PIX', 'Cartão de Crédito', 'Boleto Bancário'];
    if (!in_array($method, $allowedMethods, true)) throw new RuntimeException('Forma de pagamento inválida.');
    $statusMap = ['approve'=>'Aprovado','reject'=>'Recusado','pending'=>'Pendente'];
    $paymentStatus = $statusMap[$result] ?? null;
    if (!$paymentStatus) throw new RuntimeException('Resultado de pagamento inválido.');

    $payments = load_json('payments');
    $payments[] = [
        'id'=>next_id($payments),
        'order_id'=>$orderId,
        'method'=>$method,
        'status'=>$paymentStatus,
        'amount'=>(float)$order['total'],
        'reference'=>'MVP-' . strtoupper(bin2hex(random_bytes(3))),
        'created_at'=>now_iso(),
    ];
    save_json('payments', $payments);

    if ($paymentStatus !== 'Aprovado') return;

    // Idempotência simples: se já houver outro pagamento aprovado, não baixa estoque novamente.
    $approvedCount = 0;
    foreach ($payments as $p) {
        if ((int)$p['order_id'] === $orderId && $p['status'] === 'Aprovado') $approvedCount++;
    }
    if ($approvedCount > 1) return;

    $orderItems = array_values(array_filter(load_json('order_items'), fn($i) => (int)$i['order_id'] === $orderId));
    $products = load_json('products');
    foreach ($orderItems as $oi) {
        foreach ($products as &$p) {
            if ((int)$p['id'] === (int)$oi['product_id']) {
                if ((int)$p['stock'] < (int)$oi['quantity']) throw new RuntimeException('Estoque insuficiente no momento do pagamento.');
                $p['stock'] = (int)$p['stock'] - (int)$oi['quantity'];
                break;
            }
        }
        unset($p);
    }
    save_json('products', $products);
    set_order_status($orderId, 'Pago', $userId);
    update_row('carts', (int)$order['cart_id'], function(array $c): array {
        $c['status'] = 'Finalizado';
        $c['updated_at'] = now_iso();
        return $c;
    });
    $cartItems = load_json('cart_items');
    $cartItems = array_values(array_filter($cartItems, fn($i) => (int)$i['cart_id'] !== (int)$order['cart_id']));
    save_json('cart_items', $cartItems);
}

function cancel_order(int $orderId, array $actor, string $reason = ''): void {
    $order = find_by_id('orders', $orderId);
    if (!$order) throw new RuntimeException('Pedido não encontrado.');
    $status = $order['status'];
    $isAdmin = ($actor['profile'] ?? '') === 'Administrador';
    $isOwner = (int)$order['user_id'] === (int)$actor['id'];
    if (!$isAdmin && !$isOwner) throw new RuntimeException('Sem permissão para cancelar este pedido.');
    if (in_array($status, ['Enviado','Entregue','Cancelado'], true)) throw new RuntimeException('Este pedido não pode ser cancelado pelo fluxo comum.');
    if (in_array($status, ['Pago','Em Separação'], true) && !$isAdmin) throw new RuntimeException('Pedidos pagos ou em separação só podem ser cancelados por administrador.');

    if (in_array($status, ['Pago','Em Separação'], true)) {
        $items = array_values(array_filter(load_json('order_items'), fn($i) => (int)$i['order_id'] === $orderId));
        $products = load_json('products');
        foreach ($items as $oi) {
            foreach ($products as &$p) {
                if ((int)$p['id'] === (int)$oi['product_id']) {
                    $p['stock'] = (int)$p['stock'] + (int)$oi['quantity'];
                    break;
                }
            }
            unset($p);
        }
        save_json('products', $products);
        $payments = load_json('payments');
        foreach ($payments as &$pay) {
            if ((int)$pay['order_id'] === $orderId && $pay['status'] === 'Aprovado') {
                $pay['status'] = 'Estornado';
            }
        }
        unset($pay);
        save_json('payments', $payments);
    }

    update_row('orders', $orderId, function(array $o) use ($reason): array {
        $o['cancel_reason'] = $reason ?: 'Cancelamento solicitado';
        $o['updated_at'] = now_iso();
        return $o;
    });
    set_order_status($orderId, 'Cancelado', (int)$actor['id']);
    if ($status === 'Aguardando Pagamento') {
        update_row('carts', (int)$order['cart_id'], function(array $c): array {
            $c['status'] = 'Aberto';
            $c['updated_at'] = now_iso();
            return $c;
        });
    }
}

function can_deactivate_user(array $target): bool {
    if (($target['profile'] ?? '') === 'Administrador') return true;
    foreach (load_json('orders') as $o) {
        if ((int)$o['user_id'] === (int)$target['id'] && in_array($o['status'], ['Aguardando Pagamento','Pago','Em Separação','Enviado'], true)) return false;
    }
    return true;
}

function reset_seed_data(): void {
    ensure_data_dir();
    $users = [
        [
            'id'=>1,'name'=>'Administrador Mary','email'=>'admin@marymodas.local','phone'=>'(44) 99999-0000',
            'password_hash'=>password_hash('admin123', PASSWORD_DEFAULT),'profile'=>'Administrador','status'=>'Ativo',
            'cep'=>'87500-000','street'=>'Avenida Brasil','number'=>'120','created_at'=>now_iso(),
        ],
        [
            'id'=>2,'name'=>'Ana Cliente','email'=>'cliente@marymodas.local','phone'=>'(44) 98888-1111',
            'password_hash'=>password_hash('cliente123', PASSWORD_DEFAULT),'profile'=>'Cliente','status'=>'Ativo',
            'cep'=>'87500-100','street'=>'Rua das Flores','number'=>'45','created_at'=>now_iso(),
        ],
    ];
    save_json('users', $users);
    save_json('categories', [
        ['id'=>1,'name'=>'Vestidos','description'=>'Vestidos casuais e sociais','status'=>'Ativo'],
        ['id'=>2,'name'=>'Blusas','description'=>'Blusas e camisetas','status'=>'Ativo'],
        ['id'=>3,'name'=>'Acessórios','description'=>'Bolsas e acessórios','status'=>'Ativo'],
    ]);
    save_json('suppliers', [
        ['id'=>1,'name'=>'Fornecedor Exemplo Ltda.','document'=>'12.345.678/0001-90','phone'=>'(44) 3333-0000','email'=>'contato@fornecedor.com','status'=>'Ativo'],
        ['id'=>2,'name'=>'Moda Sul Distribuidora','document'=>'98.765.432/0001-10','phone'=>'(44) 3222-5555','email'=>'vendas@modasul.com','status'=>'Ativo'],
    ]);
    save_json('products', [
        ['id'=>1,'name'=>'Vestido Floral','description'=>'Vestido floral confeccionado em tecido leve.','category_id'=>1,'supplier_id'=>1,'price'=>129.90,'size'=>'M','color'=>'Preto','stock'=>20,'image'=>'','status'=>'Ativo'],
        ['id'=>2,'name'=>'Vestido Midi','description'=>'Vestido midi elegante para eventos.','category_id'=>1,'supplier_id'=>2,'price'=>159.90,'size'=>'G','color'=>'Vinho','stock'=>8,'image'=>'','status'=>'Ativo'],
        ['id'=>3,'name'=>'Blusa Básica','description'=>'Blusa confortável para o dia a dia.','category_id'=>2,'supplier_id'=>1,'price'=>69.90,'size'=>'P','color'=>'Branco','stock'=>35,'image'=>'','status'=>'Ativo'],
        ['id'=>4,'name'=>'Bolsa Urbana','description'=>'Bolsa compacta para uso diário.','category_id'=>3,'supplier_id'=>2,'price'=>99.90,'size'=>'Único','color'=>'Caramelo','stock'=>12,'image'=>'','status'=>'Ativo'],
    ]);
    save_json('promotions', [
        ['id'=>1,'name'=>'Semana Mary','mode'=>'Automática','code'=>'','discount_type'=>'Percentual','discount_value'=>10,'start_date'=>date('Y-m-d', strtotime('-7 days')),'end_date'=>date('Y-m-d', strtotime('+30 days')),'application'=>'Categoria','target_id'=>1,'status'=>'Ativa','used'=>false],
        ['id'=>2,'name'=>'Cupom MARY20','mode'=>'Cupom','code'=>'MARY20','discount_type'=>'Percentual','discount_value'=>20,'start_date'=>date('Y-m-d', strtotime('-1 day')),'end_date'=>date('Y-m-d', strtotime('+30 days')),'application'=>'Geral','target_id'=>null,'status'=>'Ativa','used'=>false],
    ]);
    save_json('carts', []);
    save_json('cart_items', []);
    save_json('orders', []);
    save_json('order_items', []);
    save_json('payments', []);
    save_json('order_history', []);
}

function ensure_seeded(): void {
    ensure_data_dir();
    if (!file_exists(data_file('users'))) reset_seed_data();
}

function csv_download(string $filename, array $headers, array $rows): never {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) fputcsv($out, $row, ';');
    fclose($out);
    exit;
}

ensure_seeded();
