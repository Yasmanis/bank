<?php

namespace Tests\Feature;

use App\Models\AdminConfig;
use App\Models\Bank; // Nuevo
use App\Models\BankList;
use App\Models\DailyNumber;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettlementService $service;
    private User $admin;
    private User $user;
    private Bank $bank; // Instancia para los tests

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        // 2. CREAR UN BANCO (Requisito para la nueva arquitectura)
        $this->bank = Bank::create([
            'name' => 'Banco de Prueba',
            'admin_id' => $this->admin->id,
            'is_active' => true
        ]);

        $this->service = app(SettlementService::class);

        // 3. Configuración Maestra
        AdminConfig::create([
            'user_id' => $this->admin->id,
            'fixed' => 80,
            'hundred' => 1000,
            'parlet' => 200,
            'triplet' => 70,
            'runner1' => 25,
            'runner2' => 25,
            'commission' => 20.00,
            'created_by' => $this->admin->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    #[Test]
    public function it_calculates_a_settlement_with_fixed_and_parlet_winners()
    {
        $date = '2026-02-11';
        $hourly = 'am';

        DailyNumber::create([
            'hundred' => '1',
            'fixed' => '50',
            'runner1' => '20',
            'runner2' => '30',
            'hourly' => $hourly,
            'date' => $date,
            'created_by' => $this->admin->id
        ]);

        $processedText = [
            'total' => 100,
            'bets' => [
                ['type' => 'fixed', 'number' => '50', 'amount' => 10, 'runner1' => 0, 'runner2' => 0],
                ['type' => 'parlet', 'number' => '20x30', 'amount' => 5],
                ['type' => 'fixed', 'number' => '00', 'amount' => 85, 'runner1' => 0, 'runner2' => 0],
            ]
        ];

        // 4. Asignamos el bank_id a la lista
        BankList::create([
            'user_id' => $this->user->id,
            'bank_id' => $this->bank->id, // <--- Relación con banco
            'hourly' => $hourly,
            'text' => 'texto sucio',
            'processed_text' => $processedText,
            'created_at' => $date . ' 10:00:00'
        ]);

        // 5. Llamamos al servicio con el nuevo parámetro bank_id
        $result = $this->service->calculate($this->user->id, $this->bank->id, $date, $hourly);

        $this->assertEquals(100, $result->total_sales);
        $this->assertEquals(20, $result->commission_amt);
        $this->assertEquals(1800, $result->total_prizes);
        $this->assertEquals(-1720, $result->final_balance);
    }

    #[Test]
    public function it_uses_user_specific_rates_over_admin_rates()
    {
        $this->user->userConfig()->create([
            'fixed' => 90,
            'hundred' => 1000,
            'parlet' => 200,
            'triplet' => 70,
            'runner1' => 25,
            'runner2' => 25,
            'commission' => 10.00,
        ]);

        $date = '2026-02-11';
        $hourly = 'pm';

        DailyNumber::create([
            'hundred' => '1', 'fixed' => '05', 'runner1' => '10', 'runner2' => '20',
            'hourly' => $hourly, 'date' => $date, 'created_by' => $this->admin->id
        ]);

        BankList::create([
            'user_id' => $this->user->id,
            'bank_id' => $this->bank->id, // <--- Relación con banco
            'hourly' => $hourly,
            'processed_text' => [
                'total' => 100,
                'bets' => [
                    ['type' => 'fixed', 'number' => '05', 'amount' => 10, 'runner1' => 0, 'runner2' => 0],
                ]
            ],
            'created_at' => $date . ' 18:00:00',
            'text' => 'texto sucio'
        ]);

        // 6. Actualizamos el llamado aquí también
        $result = $this->service->calculate($this->user->id, $this->bank->id, $date, $hourly);

        $this->assertEquals(900, $result->total_prizes);
        $this->assertEquals(10, $result->commission_amt);
    }
}
