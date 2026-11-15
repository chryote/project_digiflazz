<?php

namespace App\Console\Commands;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestApisCommand extends Command
{
    protected $signature = 'digiflazz:test-apis';
    protected $description = 'Run end-to-end integration tests verifying APIs and .env constants';

    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info('       DIGIFLAZZ END-TO-END STACK & API VERIFICATION       ');
        $this->info('===========================================================');

        // 1. Verify .env constants
        $username = DigiflazzConstant::username();
        $devKey = DigiflazzConstant::developmentKey();

        $this->comment('Checking .env configuration:');
        if (empty($username) || empty($devKey)) {
            $this->error(' [FAILED] DIGIFLAZZ_USERNAME or DIGIFLAZZ_DEVELOPMENT_KEY is missing in .env!');
            return Command::FAILURE;
        }

        $this->line(" - Sourced Username: <info>{$username}</info>");
        $this->line(" - Sourced Dev Key:  <info>" . substr($devKey, 0, 8) . '...' . substr($devKey, -4) . '</info>');
        $this->info(' [PASSED] Environment constants successfully loaded from .env');
        $this->newLine();

        // 2. Ensure Database is seeded
        $this->comment('Seeding database with merchant and customer testcases...');
        $this->callSilently('db:seed', ['--class' => UserSeeder::class]);
        $this->callSilently('db:seed', ['--class' => PlnCustomerSeeder::class]);
        $this->info(' [PASSED] Database ready and seeded.');
        $this->newLine();

        // 3. Test API Login
        $this->comment('Testing API Login (/api/v1/auth/login) using .env username...');
        $loginReq = Request::create('/api/v1/auth/login', 'POST', [
            'username' => $username,
            'password' => 'password123',
            'device_name' => 'E2E Verification Client',
        ]);
        $loginReq->headers->set('Accept', 'application/json');
        $loginRes = app()->handle($loginReq);

        if ($loginRes->getStatusCode() !== 200) {
            $this->error(" [FAILED] Login returned status code {$loginRes->getStatusCode()}: " . $loginRes->getContent());
            return Command::FAILURE;
        }

        $loginData = json_decode($loginRes->getContent(), true);
        $token = $loginData['data']['token']['access_token'] ?? null;
        $this->info(" [PASSED] Login successful! Bearer token issued: " . substr($token, 0, 15) . '...');
        $this->newLine();

        // 4. Test Authenticated Profile
        $this->comment('Testing Protected Profile (/api/v1/auth/me) with Bearer token...');
        $meReq = Request::create('/api/v1/auth/me', 'GET');
        $meReq->headers->set('Accept', 'application/json');
        $meReq->headers->set('Authorization', 'Bearer ' . $token);
        $meRes = app()->handle($meReq);

        if ($meRes->getStatusCode() !== 200) {
            $this->error(" [FAILED] Profile returned status code {$meRes->getStatusCode()}");
            return Command::FAILURE;
        }
        $meData = json_decode($meRes->getContent(), true);
        $this->info(" [PASSED] Profile authenticated! User: {$meData['data']['name']} ({$meData['data']['username']})");
        $this->newLine();

        // 5. Test PLN Inquiry (Prepaid 1234554321)
        $this->comment('Testing PLN Inquiry (/api/v1/inquiry-pln) with .env credentials...');
        $customerPrepaid = '1234554321';
        $signInq1 = md5($username . $devKey . $customerPrepaid);
        $inqReq1 = Request::create('/api/v1/inquiry-pln', 'POST', [
            'username' => $username,
            'customer_no' => $customerPrepaid,
            'sign' => $signInq1,
        ]);
        $inqReq1->headers->set('Accept', 'application/json');
        $inqRes1 = app()->handle($inqReq1);
        $inqData1 = json_decode($inqRes1->getContent(), true);

        if ($inqData1['data']['status'] !== 'Sukses' || $inqData1['data']['name'] !== 'DAVID') {
            $this->error(' [FAILED] Prepaid Inquiry did not return expected DAVID payload: ' . $inqRes1->getContent());
            return Command::FAILURE;
        }
        $this->info(" [PASSED] Inquiry Prepaid valid: Name={$inqData1['data']['name']}, Power={$inqData1['data']['segment_power']}");

        // 6. Test PLN Inquiry (Postpaid 1 Tagihan 530000000001)
        $customerPostpaid = '530000000001';
        $signInq2 = md5($username . $devKey . $customerPostpaid);
        $inqReq2 = Request::create('/api/v1/inquiry-pln', 'POST', [
            'username' => $username,
            'customer_no' => $customerPostpaid,
            'sign' => $signInq2,
            'ref_id' => 'E2E-INQ-001',
        ]);
        $inqReq2->headers->set('Accept', 'application/json');
        $inqRes2 = app()->handle($inqReq2);
        $inqData2 = json_decode($inqRes2->getContent(), true);

        if ($inqData2['data']['status'] !== 'Sukses' || $inqData2['data']['price'] !== 10000) {
            $this->error(' [FAILED] Postpaid Inquiry failed: ' . $inqRes2->getContent());
            return Command::FAILURE;
        }
        $this->info(" [PASSED] Inquiry Postpaid valid: Customer={$inqData2['data']['customer_name']}, Price={$inqData2['data']['price']}, Admin={$inqData2['data']['admin']}");
        $this->newLine();

        // 7. Test PLN Transaction (Payment 530000000001)
        $this->comment('Testing PLN Payment Transaction (/api/v1/transaction)...');
        $payRefId = 'E2E-PAY-' . uniqid();
        $signPay = md5($username . $devKey . $payRefId);
        $payReq = Request::create('/api/v1/transaction', 'POST', [
            'commands' => 'pay-pasca',
            'username' => $username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerPostpaid,
            'ref_id' => $payRefId,
            'sign' => $signPay,
            'testing' => true,
        ]);
        $payReq->headers->set('Accept', 'application/json');
        $payRes = app()->handle($payReq);
        $payData = json_decode($payRes->getContent(), true);

        if ($payData['data']['status'] !== 'Sukses' || empty($payData['data']['sn'])) {
            $this->error(' [FAILED] Payment transaction failed: ' . $payRes->getContent());
            return Command::FAILURE;
        }
        $this->info(" [PASSED] Payment Sukses! SN={$payData['data']['sn']}, New Balance={$payData['data']['buyer_last_saldo']}");
        $this->newLine();

        // 8. Test Cek Status with same ref_id
        $this->comment('Testing Cek Status with same ref_id...');
        $checkReq = Request::create('/api/v1/transaction', 'POST', [
            'commands' => 'pay-pasca',
            'username' => $username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerPostpaid,
            'ref_id' => $payRefId,
            'sign' => $signPay,
        ]);
        $checkReq->headers->set('Accept', 'application/json');
        $checkRes = app()->handle($checkReq);
        $checkData = json_decode($checkRes->getContent(), true);

        if ($checkData['data']['status'] !== 'Sukses' || $checkData['data']['ref_id'] !== $payRefId) {
            $this->error(' [FAILED] Cek Status failed: ' . $checkRes->getContent());
            return Command::FAILURE;
        }
        $this->info(" [PASSED] Cek Status verified! Retaining existing transaction (Idempotency OK).");
        $this->newLine();

        // 9. Test Prometheus Metrics
        $this->comment('Testing Prometheus Metrics (/metrics)...');
        $metricReq = Request::create('/metrics', 'GET');
        $metricRes = app()->handle($metricReq);
        $metricContent = $metricRes->getContent();

        if (strpos($metricContent, 'laravel_db_status') === false || strpos($metricContent, 'laravel_api_requests_total') === false) {
            $this->error(' [FAILED] /metrics does not contain expected prometheus metrics');
            return Command::FAILURE;
        }
        $this->info(' [PASSED] Prometheus metrics endpoint (/metrics) operational and healthy.');
        $this->newLine();

        $this->info('===========================================================');
        $this->info('   ALL STACK COMPONENTS & APIS TESTED SUCCESSFULLY (OK)   ');
        $this->info('===========================================================');

        return Command::SUCCESS;
    }
}
