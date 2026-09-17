<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect(current_user()['profile'] === 'ADMIN' ? 'admin/index.php' : 'store.php');
}
if (is_post()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && $user['status'] === 'Ativo' && password_verify($password, $user['password_hash'])) {
        login_user((int)$user['id']);
        flash('success', 'Login realizado com sucesso.');
        redirect($user['profile'] === 'ADMIN' ? 'admin/index.php' : 'store.php');
    }
    flash('danger', 'E-mail ou senha inválidos.');
}
$pageTitle = 'Entrar';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap card">
    <h1 class="center">Mary Modas</h1>
    <p class="lead center">Acesso por e-mail e senha</p>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="field" style="margin-top:14px"><label>Senha</label><input type="password" name="password" required></div>
        <button class="btn btn-primary" style="width:100%;margin-top:18px">Entrar</button>
    </form>
    <div class="card" style="margin-top:18px;background:#faf5f6">
        <strong>Contas de demonstração</strong><br>
        <span class="muted">Admin:</span> admin@marymodas.local / admin123<br>
        <span class="muted">Cliente:</span> cliente@marymodas.local / cliente123
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
