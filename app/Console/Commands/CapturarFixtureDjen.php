<?php

namespace App\Console\Commands;

use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Faz uma chamada REAL à Comunica API e salva a resposta como fixture de
 * teste. Precisa rodar de um IP no Brasil (a API bloqueia o exterior).
 *
 * Exemplos:
 *   php artisan djen:capturar-fixture --oab=12345 --uf=AM
 *   php artisan djen:capturar-fixture --endpoint=comunicacao/tribunal --saida=tests/Fixtures/djen/tribunais.json
 *   php artisan djen:capturar-fixture --param=numeroProcesso=00000000000000000000
 */
#[Signature('djen:capturar-fixture
    {--endpoint=comunicacao : Caminho relativo à base URL}
    {--oab= : Número da OAB (parâmetro numeroOab)}
    {--uf= : UF da OAB (parâmetro ufOab)}
    {--pagina=1 : Parâmetro pagina}
    {--itens=5 : Parâmetro itensPorPagina}
    {--param=* : Parâmetros extras no formato nome=valor}
    {--saida=tests/Fixtures/djen/comunicacao.json : Arquivo de saída (os cabeçalhos vão para *.headers.json)}')]
#[Description('Consulta a Comunica API (DJEN) e salva a resposta real como fixture de teste')]
class CapturarFixtureDjen extends Command
{
    public function handle(DjenClient $client): int
    {
        $endpoint = (string) $this->option('endpoint');
        $query = $endpoint === 'comunicacao' ? [
            'numeroOab' => $this->option('oab'),
            'ufOab' => $this->option('uf'),
            'pagina' => $this->option('pagina'),
            'itensPorPagina' => $this->option('itens'),
        ] : [];

        foreach ((array) $this->option('param') as $pair) {
            [$name, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');

            if ($name === '') {
                $this->error("Parâmetro inválido: {$pair} (use nome=valor).");

                return self::INVALID;
            }

            $query[$name] = $value;
        }

        $this->components->info('GET '.config('djen.base_url').'/'.$endpoint.'?'.http_build_query(DjenClient::normalizeQuery($query)));

        try {
            $response = $client->get($endpoint, $query);
        } catch (DjenException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $saida = base_path((string) $this->option('saida'));
        $headersPath = preg_replace('/\.json$/', '', $saida).'.headers.json';
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        File::ensureDirectoryExists(dirname($saida));
        File::put($saida, json_encode($response->json, $flags)."\n");
        File::put($headersPath, json_encode(['status' => $response->status, 'headers' => $response->headers], $flags)."\n");

        $this->components->info("Resposta salva em {$saida}");
        $this->components->info("Cabeçalhos salvos em {$headersPath}");
        $this->components->warn('LGPD: revise o arquivo e anonimize nomes/documentos antes de commitar, mantendo chaves e tipos.');

        return self::SUCCESS;
    }
}
