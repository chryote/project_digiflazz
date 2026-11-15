<?php

namespace Database\Seeders;

use App\Models\PlnCustomer;
use Illuminate\Database\Seeder;

class PlnCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'customer_no' => '1234554321',
                'customer_name' => 'DAVID',
                'meter_no' => '1234554321',
                'subscriber_id' => '523300817840',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 2500,
                'price' => 10000,
                'selling_price' => 11000,
                'desc' => null,
                'behavior' => 'prepaid_inquiry',
            ],
            [
                'customer_no' => '530000000001',
                'customer_name' => 'Nama Pelanggan Pertama',
                'meter_no' => '530000000001',
                'subscriber_id' => '530000000001',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 2500,
                'price' => 10000,
                'selling_price' => 11000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 1,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                    ],
                ],
                'behavior' => 'success_1_bill',
            ],
            [
                'customer_no' => '530000000002',
                'customer_name' => 'Nama Pelanggan Kedua',
                'meter_no' => '530000000002',
                'subscriber_id' => '530000000002',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 5000,
                'price' => 23000,
                'selling_price' => 25000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 2,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                        [
                            'periode' => '201902',
                            'nilai_tagihan' => '11500',
                            'admin' => '2500',
                            'denda' => '0',
                            'meter_awal' => '00090000',
                            'meter_akhir' => '00095000',
                        ],
                    ],
                ],
                'behavior' => 'success_2_bills',
            ],
            [
                'customer_no' => '530000000003',
                'customer_name' => 'Inquiry Gagal User',
                'meter_no' => null,
                'subscriber_id' => null,
                'segment_power' => null,
                'buyer_sku_code' => 'pln',
                'admin' => 0,
                'price' => 0,
                'selling_price' => 0,
                'desc' => null,
                'behavior' => 'inquiry_failed',
            ],
            [
                'customer_no' => '530000000006',
                'customer_name' => 'Nama Pelanggan Keenam',
                'meter_no' => '530000000006',
                'subscriber_id' => '530000000006',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 2500,
                'price' => 10000,
                'selling_price' => 11000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 1,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                    ],
                ],
                'behavior' => 'payment_failed',
            ],
            [
                'customer_no' => '630000000001',
                'customer_name' => 'Nama Pelanggan Pending 1',
                'meter_no' => '630000000001',
                'subscriber_id' => '630000000001',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 2500,
                'price' => 10000,
                'selling_price' => 11000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 1,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                    ],
                ],
                'behavior' => 'pending_callback_success_1',
            ],
            [
                'customer_no' => '630000000002',
                'customer_name' => 'Nama Pelanggan Pending 2',
                'meter_no' => '630000000002',
                'subscriber_id' => '630000000002',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 5000,
                'price' => 23000,
                'selling_price' => 25000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 2,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                        [
                            'periode' => '201902',
                            'nilai_tagihan' => '11500',
                            'admin' => '2500',
                            'denda' => '0',
                            'meter_awal' => '00090000',
                            'meter_akhir' => '00095000',
                        ],
                    ],
                ],
                'behavior' => 'pending_callback_success_2',
            ],
            [
                'customer_no' => '630000000006',
                'customer_name' => 'Nama Pelanggan Pending Fail',
                'meter_no' => '630000000006',
                'subscriber_id' => '630000000006',
                'segment_power' => 'R1 /000001300',
                'buyer_sku_code' => 'pln',
                'admin' => 2500,
                'price' => 10000,
                'selling_price' => 11000,
                'desc' => [
                    'tarif' => 'R1',
                    'daya' => 1300,
                    'lembar_tagihan' => 1,
                    'detail' => [
                        [
                            'periode' => '201901',
                            'nilai_tagihan' => '8000',
                            'admin' => '2500',
                            'denda' => '500',
                            'meter_awal' => '00080000',
                            'meter_akhir' => '00090000',
                        ],
                    ],
                ],
                'behavior' => 'pending_callback_failed',
            ],
        ];

        foreach ($customers as $data) {
            PlnCustomer::updateOrCreate(
                ['customer_no' => $data['customer_no']],
                $data
            );
        }
    }
}
