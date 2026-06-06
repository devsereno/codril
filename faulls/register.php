<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Cadastro</title>
  <style>body{font-family:Arial;max-width:500px;margin:40px auto;padding:20px;}</style>
</head>
<body>
  <h2>Cadastro de Usuário</h2>
  <form method="POST">
    Nome: <input type="text" name="nome" required><br><br>
    Email: <input type="email" name="email" required><br><br>
    Senha: <input type="password" name="senha" required minlength="6"><br><br>
    Pergunta de Segurança: <input type="text" name="pergunta" placeholder="Ex: Qual o nome da sua primeira escola?" required><br><br>
    Resposta: <input type="text" name="resposta" required><br><br>
    <button type="submit" name="register">Cadastrar</button>
  </form>
  <p>Já tem conta? <a href="login.php">Login</a></p>

  <?php
  if (isset($_POST['register'])) {
    $nome = $conn->real_escape_string($_POST['nome']);
    $email = $conn->real_escape_string($_POST['email']);
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $pergunta = $conn->real_escape_string($_POST['pergunta']);
    $resposta = password_hash(strtolower(trim($_POST['resposta'])), PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (nome, email, senha, pergunta_seguranca, resposta_seguranca) 
            VALUES ('$nome', '$email', '$senha', '$pergunta', '$resposta')";

    if ($conn->query($sql)) {
      echo "<p style='color:green'>✅ Cadastro feito! <a href='login.php'>Faça login</a></p>";
    } else {
      echo "<p style='color:red'>❌ Email já existe.</p>";
    }
  }
  ?>
</body>
</html>
