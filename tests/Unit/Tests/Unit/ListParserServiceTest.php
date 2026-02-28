<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Services\ListParserService;
use App\Repositories\BankList\BankListRepository;
use App\Dto\Bet\DetectedBet;
use Illuminate\Support\Collection;
use Mockery;

class ListParserServiceTest extends TestCase
{
    private ListParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = Mockery::mock(BankListRepository::class);
        $this->service = new ListParserService($repository);
    }

    #[Test]
    public function it_cleans_whatsapp_metadata_correctly()
    {
        $dirtyText = "[1/2/26, 22:12:47] Jose Carlos SF: 33-100\n[1/2/26, 22:13:00] Admin: Messages and calls are end-to-end encrypted\n05-50";

        $cleaned = $this->service->cleanWhatsAppChat($dirtyText);

        $this->assertStringContainsString("33-100", $cleaned);
        $this->assertStringContainsString("05-50", $cleaned);
        $this->assertStringNotContainsString("[1/2/26", $cleaned);
        $this->assertStringNotContainsString("encrypted", $cleaned);
    }

    #[Test]
    public function it_extracts_multiple_normal_bets_correctly()
    {
        // 1. Datos de entrada con dos apuestas
        $dirtyText = "[4/2, 9:26 p. m.] Yurislier: 77-110\n34-10";

        // 2. Probar la limpieza
        $cleaned = $this->service->cleanWhatsAppChat($dirtyText);

        // Verificamos que el ruido desapareció pero los datos quedaron
        $this->assertStringContainsString("77-110", $cleaned);
        $this->assertStringContainsString("34-10", $cleaned);
        $this->assertStringNotContainsString("Yurislier", $cleaned);
        $this->assertStringNotContainsString("[4/2", $cleaned);

        // 3. Probar la extracción
        ['bets' => $bets]  = $this->service->extractBets($cleaned);

        // IMPORTANTE: Ahora el conteo debe ser 2
        $this->assertCount(2, $bets);

        // 4. Verificamos la primera apuesta (77-110)
        $firstBet = $bets->first();
        $this->assertEquals('fixed', $firstBet->type);
        $this->assertEquals('77', $firstBet->number);
        $this->assertEquals(110, $firstBet->amount);

        // 5. Verificamos la segunda apuesta (34-10)
        $secondBet = $bets->last();
        $this->assertEquals('fixed', $secondBet->type);
        $this->assertEquals('34', $secondBet->number);
        $this->assertEquals(10, $secondBet->amount);
    }

    #[Test]
    public function it_extracts_all_parejas_formats_correctly()
    {
        $formats = [
            "las parejas-50",
            "parejas-50",
            "00-99-50",
            "del 00 al 99-50",
            "00 al 99-50",
            "00 al 99=50",
            "Las parejas 50",
            "Las parejas -50",
            "todas parejas con 50",
            "P-50",
            "P-a-50",
            "pare 50"
        ];

        foreach ($formats as $text) {
            ['bets' => $bets]  = $this->service->extractBets($text);

            // Verificamos que para cada formato genere las 10 parejas
            $this->assertCount(10, $bets, "Falló al procesar el formato: $text");

            $this->assertEquals(50, $bets->first()->amount);
            $this->assertEquals('00', $bets->first()->number);
            $this->assertEquals('99', $bets->last()->number);
        }
    }

    #[Test]
    public function it_extracts_all_terminales_formats_correctly()
    {
        $formats = [
            "del 07 al 97-100",    // Formato largo
            "terminales 7-100",    // Palabra completa
            "07-97-100",           // Rango numérico
            "ter 7 100",           // Abreviado con espacio
            "t 7=100",             // Letra sola con igual
            "terminal 7_100",// Singular con guion bajo
            "terminal 7-100",// Singular con guion medio
            "del 7 al 97 con 100",  // Con conectores
            "07 al 97-100",
            "Los terminales 7-100",
            "T7-100",
            "T-7-100",
            "Terminal 7con 100"
        ];

        foreach ($formats as $text) {
            ['bets' => $bets]  = $this->service->extractBets($text);

            $this->assertCount(10, $bets, "Falló en el formato: $text");

            // Verificamos el primer y último número generado
            $this->assertEquals('07', $bets->first()->number);
            $this->assertEquals('97', $bets->last()->number);

            // Verificamos el monto
            $this->assertEquals(100, $bets->first()->amount);
        }
    }
    #[Test]
    public function it_extracts_all_parlet_formats_correctly()
    {
        $formats = [
            "05x10-50",    // Estándar
            "38*70-100",   // Con asterisco
            "5x7 20",      // Números de 1 cifra (debe ser 05x07)
            "70x38=15",    // Al revés y con igual (debe ser 38x70)
            "01x02_10",    // Con guion bajo
            "01x02 con 10", // Con palabra "con"
            "20×23-100" // Con signo ×
        ];

        // 1. Probar 05x10-50
        ['bets' => $bets]  = $this->service->extractBets($formats[0]);
        $this->assertCount(1, $bets);
        $this->assertEquals('parlet', $bets->first()->type);
        $this->assertEquals('05x10', $bets->first()->number);
        $this->assertEquals(50, $bets->first()->amount);

        // 2. Probar con asterisco 38*70-100
        ['bets' => $bets]  = $this->service->extractBets($formats[1]);
        $this->assertCount(1, $bets);
        $this->assertEquals('38x70', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);

        // 3. Probar autocompletado de ceros: 5x7 -> 05x07
        ['bets' => $bets]  = $this->service->extractBets($formats[2]);
        $this->assertCount(1, $bets);
        $this->assertEquals('05x07', $bets->first()->number);
        $this->assertEquals(20, $bets->first()->amount);

        // 4. Probar ordenamiento automático: 70x38 -> 38x70
        ['bets' => $bets]  = $this->service->extractBets($formats[3]);
        $this->assertCount(1, $bets);
        $this->assertEquals('38x70', $bets->first()->number);
        $this->assertEquals(15, $bets->first()->amount);

        // 5. Probar con guion bajo: 01x02_10
        ['bets' => $bets]  = $this->service->extractBets($formats[4]);
        $this->assertCount(1, $bets);
        $this->assertEquals('01x02', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);

        // 6. Probar con palabra conectora: 01x02 con 10
        ['bets' => $bets]  = $this->service->extractBets($formats[5]);
        $this->assertCount(1, $bets);
        $this->assertEquals('01x02', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);

        // 7. Probar con signo ×: 20×23-100
        ['bets' => $bets]  = $this->service->extractBets($formats[6]);
        $this->assertCount(1, $bets);
        $this->assertEquals('20x23', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);
    }

    #[Test]
    public function it_extracts_all_normal_bet_variations_correctly()
    {
        $formats = [
            "33-100-20-10",   // Fijo + C1 + C2
            "44 50 10",       // Fijo + C1 (con espacios)
            "55=30",          // Solo Fijo (con igual)
            "123-500",        // Centena (3 dígitos)
            "t05-100-20",     // Formato con 't' inicial
            "99-200-0-50",    // Fijo + C2 (C1 en cero)
            "07-15-10-10",    // Ceros a la izquierda
            "38*30",
            "20*20_5_5",
            "20×23",
            "10,25,30-5",
            "04con 40",
            "Del 01 al 10 con 100"
        ];

        // 1. Caso completo: 33-100-20-10
        ['bets' => $bets]  = $this->service->extractBets($formats[0]);
        $this->assertEquals('33', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);
        $this->assertEquals(20, $bets->first()->runner1);
        $this->assertEquals(10, $bets->first()->runner2);

        // 2. Fijo + C1: 44 50 10
        ['bets' => $bets]  = $this->service->extractBets($formats[1]);
        $this->assertEquals('44', $bets->first()->number);
        $this->assertEquals(50, $bets->first()->amount);
        $this->assertEquals(10, $bets->first()->runner1);
        $this->assertEquals(0, $bets->first()->runner2);

        // 3. Solo Fijo: 55=30
        ['bets' => $bets]  = $this->service->extractBets($formats[2]);
        $this->assertEquals('55', $bets->first()->number);
        $this->assertEquals(30, $bets->first()->amount);
        $this->assertEquals(0, $bets->first()->runner1);
        $this->assertEquals(0, $bets->first()->runner2);

        // 4. Centena: 123-500 (Debe ser tipo 'hundred')
        ['bets' => $bets]  = $this->service->extractBets($formats[3]);
        $this->assertEquals('hundred', $bets->first()->type);
        $this->assertEquals('123', $bets->first()->number);
        $this->assertEquals(500, $bets->first()->amount);

        // 5. Con 't' inicial: t05-100-20
        ['bets' => $bets]  = $this->service->extractBets($formats[4]);
        $this->assertEquals('05', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);
        $this->assertEquals(20, $bets->first()->runner1);

        // 6. Fijo + C2 (C1 en cero): 99-200-0-50
        ['bets' => $bets]  = $this->service->extractBets($formats[5]);
        $this->assertEquals(200, $bets->first()->amount);
        $this->assertEquals(0, $bets->first()->runner1);
        $this->assertEquals(50, $bets->first()->runner2);
        // 7. Fijo + C2 (C1 en cero): 99-200-0-50
        ['bets' => $bets]  = $this->service->extractBets($formats[6]);
        $this->assertEquals('07', $bets->first()->number);
        $this->assertEquals(15, $bets->first()->amount);
        $this->assertEquals(10, $bets->first()->runner1);
        $this->assertEquals(10, $bets->first()->runner2);
        //"38*30"
        ['bets' => $bets]  = $this->service->extractBets($formats[7]);
        $this->assertEquals('38', $bets->first()->number);
        $this->assertEquals(30, $bets->first()->amount);
        //"20*20_5_5"
        ['bets' => $bets]  = $this->service->extractBets($formats[8]);
        $this->assertEquals('20', $bets->first()->number);
        $this->assertEquals(20, $bets->first()->amount);
        $this->assertEquals(5, $bets->first()->runner1);
        $this->assertEquals(5, $bets->first()->runner2);

        //"20×23"
        ['bets' => $bets]  = $this->service->extractBets($formats[9]);
        $this->assertEquals('20', $bets->first()->number);
        $this->assertEquals(23, $bets->first()->amount);

        //"10,25,30-5"
        ['bets' => $bets]  = $this->service->extractBets($formats[10]);
        $this->assertEquals('10', $bets[0]->number);
        $this->assertEquals(5, $bets[0]->amount);
        $this->assertEquals('25', $bets[1]->number);
        $this->assertEquals(5, $bets[1]->amount);
        $this->assertEquals('30', $bets[2]->number);
        $this->assertEquals(5, $bets[2]->amount);

        //04con 40
        ['bets' => $bets]  = $this->service->extractBets($formats[11]);
        $this->assertEquals('fixed', $bets[0]->type);
        $this->assertEquals('04', $bets[0]->number);
        $this->assertEquals(40, $bets[0]->amount);

    }

    /** @test */
    public function it_extracts_linear_ranges_correctly_without_confusing_with_terminales()
    {
        $text = "Del 01 al 10 con 100";
        ['bets' => $bets] = $this->service->extractBets($text);

        // Debe generar 10 apuestas: 01, 02, 03, 04, 05, 06, 07, 08, 09, 10
        $this->assertCount(10, $bets);

        $this->assertEquals('01', $bets->first()->number);
        $this->assertEquals('10', $bets->last()->number);

        // Verificamos un número intermedio para asegurar que es lineal y no terminal
        // (Si fuera terminal, el segundo número sería 11, pero aquí debe ser 02)
        $this->assertEquals('02', $bets->get(1)->number);
        $this->assertEquals(100, $bets->first()->amount);
    }

    #[Test]
    public function it_extracts_todos_los_format_correctly()
    {
        $formats = [
            "50 pesos a todos los 70",
            "100 a todos los 30",
            "20 pesos todos los 00",
            "10 todos 80"
        ];

        // 1. Probar "50 pesos a todos los 70"
        ['bets' => $bets]  = $this->service->extractBets($formats[0]);
        $this->assertCount(10, $bets);
        $this->assertEquals('70', $bets->first()->number);
        $this->assertEquals('79', $bets->last()->number);
        $this->assertEquals(50, $bets->first()->amount);

        // 2. Probar "100 a todos los 30"
        ['bets' => $bets]  = $this->service->extractBets($formats[1]);
        $this->assertEquals('30', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);

        // 3. Probar "10 todos 80" (formato ultra corto)
        ['bets' => $bets]  = $this->service->extractBets($formats[3]);
        $this->assertEquals('80', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);
    }

    #[Test]
    public function it_extracts_triplets_correctly_based_on_three_amounts_or_prefix()
    {
        $formats = [
            "t 50-10-10-10",      // Prefijo 't' + 3 montos
            "50-10-40-100",       // Sin prefijo pero con 3 montos
            "los 70-10-10-10",    // Línea con 3 montos
            "ter 5-5-5-5",        // Terminal con 3 montos
            "parejas-20-20-20"    // Parejas con 3 montos
        ];

        // 1. Caso Normal con prefijo 't'
        ['bets' => $bets] = $this->service->extractBets($formats[0]);
        $this->assertEquals('triplet', $bets->first()->type);
        $this->assertEquals(10, $bets->first()->amount);
        $this->assertEquals(10, $bets->first()->runner1);
        $this->assertEquals(10, $bets->first()->runner2);

        // 2. Caso Automático (3 montos detectados)
        ['bets' => $bets] = $this->service->extractBets($formats[1]);
        $this->assertEquals('triplet', $bets->first()->type);
        $this->assertEquals(10, $bets->first()->amount);
        $this->assertEquals(40, $bets->first()->runner1);
        $this->assertEquals(100, $bets->first()->runner2);

        // 3. Caso Línea (Genera 10 tripletas)
        ['bets' => $bets] = $this->service->extractBets($formats[2]);
        $this->assertCount(10, $bets);
        $this->assertEquals('triplet', $bets->first()->type);
        $this->assertEquals('70', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);

        // 4. Caso Terminal (Genera 10 tripletas)
        ['bets' => $bets] = $this->service->extractBets($formats[3]);
        $this->assertCount(10, $bets);
        $this->assertEquals('triplet', $bets->first()->type);
        $this->assertEquals('05', $bets->first()->number);
    }

    /** @test */
    public function it_extracts_line_range_with_underscores_as_triplet_correctly()
    {
        // Entrada: Rango 20-29 con montos 25, 20, 20 usando guiones bajos
        $text = "20_al_29_25_20_20";

        ['bets' => $bets] = $this->service->extractBets($text);

        // 1. Debe generar 10 apuestas (del 20 al 29)
        $this->assertCount(10, $bets);

        // 2. Verificamos la primera apuesta (20)
        $first = $bets->first();
        $this->assertEquals('20', $first->number);
        $this->assertEquals('triplet', $first->type); // Debe ser tripleta
        $this->assertEquals(25, $first->amount);
        $this->assertEquals(20, $first->runner1);
        $this->assertEquals(20, $first->runner2);

        // 3. Verificamos la última apuesta (29)
        $last = $bets->last();
        $this->assertEquals('29', $last->number);
        $this->assertEquals('triplet', $last->type);
        $this->assertEquals(25, $last->amount);
    }


    #[Test]
    public function it_calculates_triplet_totals_separately_from_fixed()
    {
        $bets = collect([
            // Una apuesta fija normal
            new DetectedBet('fixed', '05', 100, 10, 10),
            // Una tripleta
            new DetectedBet('triplet', '33', 50, 50, 50),
            // Otra tripleta
            new DetectedBet('triplet', '44', 20, 20, 20),
        ]);

        $totals = $this->service->calculateTotals($bets);

        // 1. Verificamos que el Fijo normal sume 100 (la tripleta no debe entrar aquí)
        $this->assertEquals(100, $totals['fixed']);

        // 2. Verificamos que los Corridos normales sumen 10 cada uno
        $this->assertEquals(10, $totals['runner1']);
        $this->assertEquals(10, $totals['runner2']);

        // 3. Verificamos la nueva categoría de Tripletas
        // Sumamos el monto base de las tripletas: 50 + 20 = 70
        $this->assertEquals(70, $totals['triplet']);

        // 4. Verificamos el detalle de tripletas
        $this->assertArrayHasKey('33', $totals['triplet_details']);
        $this->assertEquals(50, $totals['triplet_details']['33']);
        $this->assertEquals(20, $totals['triplet_details']['44']);

        // 5. El total general debe sumar TODO el dinero:
        // (100+10+10) + (50+50+50) + (20+20+20) = 120 + 150 + 60 = 330
        $this->assertEquals(330, $totals['total']);
    }

    #[Test]
    public function it_calculates_totals_correctly()
    {
        // Creamos una colección manual de DTOs
        $bets = collect([
            new DetectedBet('fixed', '05', 100, 10, 5),
            new DetectedBet('fixed', '05', 50),
            new DetectedBet('hundred', '123', 200),
            new DetectedBet('parlet', '01x02', 300),
        ]);

        $totals = $this->service->calculateTotals($bets);

        // Verificamos sumas globales
        $this->assertEquals(150, $totals['fixed']); // 100 + 50
        $this->assertEquals(200, $totals['hundred']);
        $this->assertEquals(300, $totals['parlet']);
        $this->assertEquals(10, $totals['runner1']);
        $this->assertEquals(5, $totals['runner2']);

        // El total debe ser: (100+10+5) + 50 + 200 + 300 = 665
        $this->assertEquals(665, $totals['total']);

        // Verificar detalle específico
        $this->assertEquals(150, $totals['fixed_details']['05']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
