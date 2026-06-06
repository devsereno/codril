<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Recuperar Senha</title>
  <style>body{font-family:Arial;max-width:500px;margin:40px auto;padding:20px;}</style>
</head>
<body>
  <h2>Recuperar Senha</h2>
  <form method="POST">
    Email: <input type="email" name="email" required><br><br>
    <button type="submit" name="verificar">Continuar</button>
  </form>

  <?php
  if (isset($_POST['verificar'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $result = $conn->query("SELECT pergunta_seguranca FROM usuarios WHERE email='$email'");
    if ($row = $result->fetch_assoc()) {
      echo "<form method='POST'>
        <input type='hidden' name='email' value='$email'>
        <p><strong>Pergunta:</strong> " . htmlspecialchars($row['pergunta_seguranca']) . "</p>
        Resposta: <input type='text' name='resposta' required><br><br>
        Nova Senha: <input type='password' name='nova_senha' required><br><br>
        <button type='submit' name='reset'>Redefinir Senha</button>
      </form>";
    } else {
      echo "<p style='color:red'>Email não encontrado.</p>";
    }
  }

  if (isset($_POST['reset'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $resposta = strtolower(trim($_POST['resposta']));
    $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);

    $result = $conn->query("SELECT resposta_seguranca FROM usuarios WHERE email='$email'");
    $user = $result->fetch_assoc();

    if (password_verify($resposta, $user['resposta_seguranca'])) {
      $conn->query("UPDATE usuarios SET senha='$nova_senha' WHERE email='$email'");
      echo "<p style='color:green'>✅ Senha alterada! <a href='login.php'>Login</a></p>";
    } else {
      echo "<p style='color:red'>Resposta incorreta.</p>";
    }
  }
  ?>
</body>
</html>
