<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

use Exception;

class FixIPToCloudflare extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:fixip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'fix ip cloudflare';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $page = 100; //of page

        $client = new Client([
            'base_uri' => config('app.cloudflare_api_endpoint'),
            'time_out' => 30.0,
            'auth' => 'Bearer ' . config('app.cloudflare_api_token'),
            'headers' => [
                'X-Auth-Email' => config('app.cloudflare_api_email'),
                'X-Auth-Key' => config('app.cloudflare_api_key'),
                'Content-Type' => 'application/json',
            ]
        ]);

        try{
            $zone_list = $client->get('zones?page=' . $page)->getBody()->getContents();
            $zone_res = json_decode($zone_list, true);

            if(count($zone_res['result']) != 0){
                foreach($zone_res['result'] as $zone_item){
                    // dd($zone_item['name']);
    
                    if($zone_item['name'] != 'fastcommerz.com'){
                        $dns_list = $client->get('zones/' . $zone_item['id'] . '/dns_records?type=AAAA')->getBody()->getContents();
                        $dns_res = json_decode($dns_list, true);
    
                        // dd($dns_res);
    
                        if(isset($dns_res['result']) && !empty($dns_res['result'][0])){
                            try{
                                $update_dns = $client->put('zones/' . $zone_item['id'] . '/dns_records/' . $dns_res['result'][0]['id'], [
                                    'json' => [
                                        'type' => 'A',
                                        'name' => $zone_item['name'],
                                        'content' => '13.229.163.127',
                                        'ttl' => 3600,
                                        'proxied' => true
                                    ]
                                ])->getBody()->getContents();
        
                                $update_res = json_decode($update_dns, true);
        
                                if($update_res['success'] && !empty($update_res['result']) && empty($update_res['error'])){
                                    $this->info($zone_item['name'] . ' : ' . 'fix ip success.');
                                }else{
                                    $this->warn($zone_item['name'] . ' : ' . 'fix ip unsuccess.');
                                }
                            }catch(Exception $e){
                                $this->error($zone_item['name'] . ' : ' . 'fix ip error -> ' . $e);
                            }
                        }else{
                            $this->warn($zone_item['name'] . ' : ' . 'no AAAA record.');
                        }
                        
                    }
                }
            }else{
                $this->warn('Warning : no zone list.');
            }
            
        }catch(Exception $e){
            $this->error('Error : get zone -> ' . $e);
        }
    }
}
