<?php

namespace Tests\Unit;

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

    /** @test */
    public function test_pruebas_que_fallan()
    {
        $bets = $this->service->extractBets("50 con 50 pesos");
        $firstBet = $bets->first();
        $this->assertEquals('fixed', $firstBet->type);
        $this->assertEquals('50', $firstBet->number);
        $this->assertEquals(50, $firstBet->amount);
    }

    /** @test */
    public function it_cleans_whatsapp_metadata_correctly()
    {
        $dirtyText = "[1/2/26, 22:12:47] Jose Carlos SF: 33-100\n[1/2/26, 22:13:00] Admin: Messages and calls are end-to-end encrypted\n05-50";

        $cleaned = $this->service->cleanWhatsAppChat($dirtyText);

        $this->assertStringContainsString("33-100", $cleaned);
        $this->assertStringContainsString("05-50", $cleaned);
        $this->assertStringNotContainsString("[1/2/26", $cleaned);
        $this->assertStringNotContainsString("encrypted", $cleaned);
    }

    /** @test */
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
        $bets = $this->service->extractBets($cleaned);

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

    /** @test */
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
            "Las parejas con50"
        ];

        foreach ($formats as $text) {
            $bets = $this->service->extractBets($text);

            // Verificamos que para cada formato genere las 10 parejas
            $this->assertCount(10, $bets, "Falló al procesar el formato: $text");

            $this->assertEquals(50, $bets->first()->amount);
            $this->assertEquals('00', $bets->first()->number);
            $this->assertEquals('99', $bets->last()->number);
        }
    }

    /** @test */
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
            "07 al 97-100"
        ];

        foreach ($formats as $text) {
            $bets = $this->service->extractBets($text);

            $this->assertCount(10, $bets, "Falló en el formato: $text");

            // Verificamos el primer y último número generado
            $this->assertEquals('07', $bets->first()->number);
            $this->assertEquals('97', $bets->last()->number);

            // Verificamos el monto
            $this->assertEquals(100, $bets->first()->amount);
        }
    }
    /** @test */
    public function it_extracts_all_parlet_formats_correctly()
    {
        $formats = [
            "05x10-50",    // Estándar
            "38*70-100",   // Con asterisco
            "5x7 20",      // Números de 1 cifra (debe ser 05x07)
            "70x38=15",    // Al revés y con igual (debe ser 38x70)
            "01x02_10",    // Con guion bajo
            "01x02 Xon 10" // Con palabra "con"
        ];

        // 1. Probar 05x10-50
        $bets = $this->service->extractBets($formats[0]);
        $this->assertCount(1, $bets);
        $this->assertEquals('parlet', $bets->first()->type);
        $this->assertEquals('05x10', $bets->first()->number);
        $this->assertEquals(50, $bets->first()->amount);

        // 2. Probar con asterisco 38*70-100
        $bets = $this->service->extractBets($formats[1]);
        $this->assertCount(1, $bets);
        $this->assertEquals('38x70', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);

        // 3. Probar autocompletado de ceros: 5x7 -> 05x07
        $bets = $this->service->extractBets($formats[2]);
        $this->assertCount(1, $bets);
        $this->assertEquals('05x07', $bets->first()->number);
        $this->assertEquals(20, $bets->first()->amount);

        // 4. Probar ordenamiento automático: 70x38 -> 38x70
        $bets = $this->service->extractBets($formats[3]);
        $this->assertCount(1, $bets);
        $this->assertEquals('38x70', $bets->first()->number);
        $this->assertEquals(15, $bets->first()->amount);

        // 5. Probar con guion bajo: 01x02_10
        $bets = $this->service->extractBets($formats[4]);
        $this->assertCount(1, $bets);
        $this->assertEquals('01x02', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);

        // 6. Probar con palabra conectora: 01x02 con 10
        $bets = $this->service->extractBets($formats[5]);
        $this->assertCount(1, $bets);
        $this->assertEquals('01x02', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);
    }

    /** @test */
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
            "20*20_5_5"
        ];

        // 1. Caso completo: 33-100-20-10
        $bets = $this->service->extractBets($formats[0]);
        $this->assertEquals('33', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);
        $this->assertEquals(20, $bets->first()->runner1);
        $this->assertEquals(10, $bets->first()->runner2);

        // 2. Fijo + C1: 44 50 10
        $bets = $this->service->extractBets($formats[1]);
        $this->assertEquals('44', $bets->first()->number);
        $this->assertEquals(50, $bets->first()->amount);
        $this->assertEquals(10, $bets->first()->runner1);
        $this->assertEquals(0, $bets->first()->runner2);

        // 3. Solo Fijo: 55=30
        $bets = $this->service->extractBets($formats[2]);
        $this->assertEquals('55', $bets->first()->number);
        $this->assertEquals(30, $bets->first()->amount);
        $this->assertEquals(0, $bets->first()->runner1);
        $this->assertEquals(0, $bets->first()->runner2);

        // 4. Centena: 123-500 (Debe ser tipo 'hundred')
        $bets = $this->service->extractBets($formats[3]);
        $this->assertEquals('hundred', $bets->first()->type);
        $this->assertEquals('123', $bets->first()->number);
        $this->assertEquals(500, $bets->first()->amount);

        // 5. Con 't' inicial: t05-100-20
        $bets = $this->service->extractBets($formats[4]);
        $this->assertEquals('05', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);
        $this->assertEquals(20, $bets->first()->runner1);

        // 6. Fijo + C2 (C1 en cero): 99-200-0-50
        $bets = $this->service->extractBets($formats[5]);
        $this->assertEquals(200, $bets->first()->amount);
        $this->assertEquals(0, $bets->first()->runner1);
        $this->assertEquals(50, $bets->first()->runner2);
        // 7. Fijo + C2 (C1 en cero): 99-200-0-50
        $bets = $this->service->extractBets($formats[6]);
        $this->assertEquals('07', $bets->first()->number);
        $this->assertEquals(15, $bets->first()->amount);
        $this->assertEquals(10, $bets->first()->runner1);
        $this->assertEquals(10, $bets->first()->runner2);
        //"38*30"
        $bets = $this->service->extractBets($formats[7]);
        $this->assertEquals('38', $bets->first()->number);
        $this->assertEquals(30, $bets->first()->amount);
        //"20*20_5_5"
        $bets = $this->service->extractBets($formats[8]);
        $this->assertEquals('20', $bets->first()->number);
        $this->assertEquals(20, $bets->first()->amount);
        $this->assertEquals(5, $bets->first()->runner1);
        $this->assertEquals(5, $bets->first()->runner2);

    }

    /** @test */
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

    /** @test */
    public function it_extracts_todos_los_format_correctly()
    {
        $formats = [
            "50 pesos a todos los 70",
            "100 a todos los 30",
            "20 pesos todos los 00",
            "10 todos 80"
        ];

        // 1. Probar "50 pesos a todos los 70"
        $bets = $this->service->extractBets($formats[0]);
        $this->assertCount(10, $bets);
        $this->assertEquals('70', $bets->first()->number);
        $this->assertEquals('79', $bets->last()->number);
        $this->assertEquals(50, $bets->first()->amount);

        // 2. Probar "100 a todos los 30"
        $bets = $this->service->extractBets($formats[1]);
        $this->assertEquals('30', $bets->first()->number);
        $this->assertEquals(100, $bets->first()->amount);

        // 3. Probar "10 todos 80" (formato ultra corto)
        $bets = $this->service->extractBets($formats[3]);
        $this->assertEquals('80', $bets->first()->number);
        $this->assertEquals(10, $bets->first()->amount);
    }

    /** @test */
    public function it_ignores_lines_without_numbers()
    {
        $text = "esta es una linea de texto sin apuestas";
        $bets = $this->service->extractBets($text);

        $this->assertCount(0, $bets);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
