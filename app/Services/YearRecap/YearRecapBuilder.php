<?php

namespace App\Services\YearRecap;

use App\Models\User;
use App\Services\YearRecap\Slides\RecapSlide;
use App\Support\StoreBusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Monta o recap de um utilizador: escolhe os slides do perfil (config/year-recap.php),
 * valida as permissões de cada um e calcula os dados.
 */
class YearRecapBuilder
{
    /** Ciclo de temas (usado pelos templates Nocturne/Aurora). */
    private const THEMES = ['ink', 'rose', 'sage', 'ink', 'amber', 'rose', 'sage', 'amber'];

    /** @var array<string, class-string<RecapSlide>> */
    private const SLIDES = [
        'marcacoes' => Slides\MarcacoesSlide::class,
        'clientes' => Slides\ClientesSlide::class,
        'retorno' => Slides\RetornoSlide::class,
        'fieis' => Slides\FieisSlide::class,
        'servicos' => Slides\ServicosSlide::class,
        'unhas' => Slides\UnhasSlide::class,
        'horas' => Slides\HorasSlide::class,
        'recordes' => Slides\RecordesSlide::class,
        'horario' => Slides\HorarioSlide::class,
        'equipa' => Slides\EquipaSlide::class,
        'canais' => Slides\CanaisSlide::class,
        'marcadas_por_mim' => Slides\MarcadasPorMimSlide::class,
        'faturacao' => Slides\FaturacaoSlide::class,
        'faturacao_pessoal' => Slides\FaturacaoPessoalSlide::class,
        'pagamentos' => Slides\PagamentosSlide::class,
        'sms' => Slides\SmsSlide::class,
    ];

    /** Versão da estrutura em cache: subir quando os slides mudam. */
    private const CACHE_VERSION = 1;

    /**
     * @return array{year: int, store_name: string, audience: string, is_partial_year: bool, slides: list<array<string, mixed>>}
     */
    public function build(User $viewer, int $storeId, string $storeName, int $year, bool $refresh = false): array
    {
        $audience = RecapContext::audienceFor($viewer);
        abort_if($audience === null, 403);

        $ctx = new RecapContext(
            viewer: $viewer,
            audience: $audience,
            storeId: $storeId,
            storeName: $storeName,
            year: $year,
            timezone: StoreBusinessTime::timezoneForStore($storeId),
        );

        // A chave inclui quem vê: o conteúdo depende do perfil e das permissões.
        $key = sprintf('year-recap:v%d:%d:%d:%d:%s', self::CACHE_VERSION, $storeId, $year, $viewer->id, $audience);
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, $this->ttl($ctx), fn () => $this->compute($ctx));
    }

    /** Calcula sem cache (útil em testes). */
    public function compute(RecapContext $ctx): array
    {
        $data = new RecapData($ctx);
        $slides = [];

        if ($data->events()->count() >= (int) config('year-recap.min_marcacoes', 10)) {
            foreach ((array) config('year-recap.slides.'.$ctx->audience, []) as $key) {
                $slide = $this->buildSlide((string) $key, $ctx, $data);
                if ($slide !== null) {
                    $slides[] = $slide;
                }
            }
        }

        $themed = [];
        foreach ($slides as $i => $slide) {
            $themed[] = $slide + ['theme' => self::THEMES[$i % count(self::THEMES)]];
        }

        return [
            'year' => $ctx->year,
            'store_name' => $ctx->storeName,
            'audience' => $ctx->audience,
            'is_partial_year' => $ctx->isPartialYear,
            'slides' => array_merge(
                [$this->cover($ctx)],
                $themed,
                [$this->finale($ctx, $slides)],
            ),
        ];
    }

    private function buildSlide(string $key, RecapContext $ctx, RecapData $data): ?array
    {
        $class = self::SLIDES[$key] ?? null;
        if ($class === null) {
            return null;
        }

        /** @var RecapSlide $slide */
        $slide = app($class);
        if (! $slide->allows($ctx)) {
            return null;
        }

        try {
            $built = $slide->build($ctx, $data);
        } catch (\Throwable $e) {
            // Um slide com erro não deve deitar abaixo o recap inteiro.
            report($e);
            Log::warning('Year recap: slide falhou', ['slide' => $key, 'store' => $ctx->storeId, 'error' => $e->getMessage()]);

            return null;
        }

        if ($built === null) {
            return null;
        }

        return ['id' => $key] + array_filter($built, fn ($v) => $v !== null);
    }

    private function cover(RecapContext $ctx): array
    {
        $first = $ctx->viewerFirstName();
        $until = $ctx->isPartialYear ? ' Contas feitas até '.RecapFormat::dayMonth($ctx->end).'.' : '';

        return [
            'id' => 'cover',
            'type' => 'cover',
            'theme' => 'cover',
            'kicker' => 'Year Recap',
            'title' => (string) $ctx->year,
            'subtitle' => $ctx->isPersonal()
                ? ($first !== '' ? 'O teu ano, '.$first : 'O teu ano')
                : $ctx->storeName,
            'body' => $ctx->t(
                'Um olhar sobre o ano que construíste — marcações, clientes e recordes.',
                'Um olhar sobre o ano que a equipa construiu — marcações, clientes e momentos que importaram.',
            ).$until,
            'cta' => 'Começar',
        ];
    }

    /** @param  list<array<string, mixed>>  $slides */
    private function finale(RecapContext $ctx, array $slides): array
    {
        if ($slides === []) {
            return [
                'id' => 'finale',
                'type' => 'finale',
                'theme' => 'finale',
                'kicker' => 'Year Recap',
                'title' => 'Ainda é cedo.',
                'body' => 'Ainda não há marcações suficientes em '.$ctx->year.' para contar esta história. Volta mais perto do fim do ano.',
                'highlights' => [],
                'cta' => 'Ver de novo',
                'exit' => 'Voltar ao dashboard',
            ];
        }

        $byKey = [];
        foreach ($slides as $slide) {
            if (! empty($slide['highlight'])) {
                $byKey[$slide['id']] = $slide['highlight'];
            }
        }
        $highlights = [];
        foreach ((array) config('year-recap.finale_priority', []) as $key) {
            if (isset($byKey[$key]) && count($highlights) < 4) {
                $highlights[] = $byKey[$key];
            }
        }

        $first = $ctx->viewerFirstName();

        return [
            'id' => 'finale',
            'type' => 'finale',
            'theme' => 'finale',
            'kicker' => 'Obrigado',
            'title' => $ctx->isPersonal()
                ? ($first !== '' ? 'Que ano, '.$first.'.' : 'Que ano.')
                : 'Foi um ano cheio.',
            'body' => $ctx->t(
                'Cada marcação, cada cliente que voltou, cada recorde — tudo isto é o teu '.$ctx->year.'.',
                'Marcações, regressos, picos e calmaria — tudo isto é a história da loja em '.$ctx->year.'.',
            ),
            'highlights' => $highlights,
            'cta' => 'Ver de novo',
            'exit' => 'Voltar ao dashboard',
        ];
    }

    private function ttl(RecapContext $ctx): CarbonInterface
    {
        return $ctx->isPartialYear
            ? now()->addMinutes(max(1, (int) config('year-recap.cache_minutes', 60)))
            : now()->addDays(30);
    }
}
