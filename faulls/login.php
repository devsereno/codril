<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <style>body{font-family:Arial;max-width:500px;margin:40px auto;padding:20px;}</style>
</head>
<body>
  <h2>Login</h2>
  <form method="POST">
    Email: <input type="email" name="email" required><br><br>
    Senha: <input type="password" name="senha" required><br><br>
    <button type="submit" name="login">Entrar</button>
  </form>
  <p><a href="recover.php">Esqueci a senha</a></p>

  <?php
  if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $senha = $_POST['senha'];

    $result = $conn->query("SELECT * FROM usuarios WHERE email='$email'");
    if ($user = $result->fetch_assoc()) {
      if (password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nome'] = $user['nome'];
        header("Location: index.php");
        exit;
      }
    }
    echo "<p style='color:red'>Email ou senha incorretos.</p>";
  }
  ?>
</body>
</html>
