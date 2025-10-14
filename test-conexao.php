<?php
// test-conexao.php
echo "PHP está funcionando!<br>";
echo "Versão do PHP: " . phpversion() . "<br>";

// Testar conexão com banco
try {
    $host = "localhost";
    $dbname = "juntas_goias";
    $username = "layane";
    $password = "92106115@Lore";
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2 style='color: green;'>✅ Conexão com banco OK!</h2>";
    
    // Testar uma query simples
    $stmt = $conn->query("SELECT COUNT(*) as total FROM municipios");
    $result = $stmt->fetch();
    echo "Total de municípios: " . $result['total'];
    
} catch(PDOException $e) {
    echo "<h2 style='color: red;'>❌ Erro de conexão:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
