<?php
// Garante que nenhum texto que o cliente final ve (cobranca, extrato, webhook) leva o nome "Chama" fixo.
// Rodar: php artisan tinker scripts/verifica-textos-marca.php
$ok = 0; $falhas = [];
$proibidos = ['Corrida Chama', 'motorista Chama', 'Comissão Chama', 'Token do webhook (App Chama)'];
foreach (['app/Domains/Payments/Services', 'app/Filament/Pages'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir))) as $f) {
        if (! str_ends_with((string) $f, '.php')) { continue; }
        $src = file_get_contents((string) $f);
        foreach ($proibidos as $p) {
            if (str_contains($src, $p)) { $falhas[] = basename((string) $f).': texto fixo "'.$p.'"'; } else { $ok++; }
        }
    }
}
echo "
TEXTOS: $ok ok, ".count($falhas)." falhas
";
foreach ($falhas as $f) { echo " - $f
"; }
