<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash('danger', 'Informe nome, e-mail válido e senha com pelo menos 8 caracteres.');
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users(name,email,password_hash,profile,status) VALUES (?,?,?,'CLIENTE','Ativo')");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Conta criada. Agora você pode entrar.');
            redirect('login.php');
        } catch (PDOException $e) {
            flash('danger', 'Este e-mail já está em uso.');
        }
    }
}
$pageTitle = 'Criar conta';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap card">
    <h1>Criar conta</h1><p class="lead">Cadastro simples por e-mail e senha.</p>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="field wide"><label>Nome completo</label><input name="name" required></div>
        <div class="field wide"><label>E-mail</label><input type="email" name="email" required></div>
        <div class="field wide"><label>Senha</label><input type="password" name="password" minlength="8" required></div>
        <div class="wide"><button class="btn btn-primary">Cadastrar</button></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
