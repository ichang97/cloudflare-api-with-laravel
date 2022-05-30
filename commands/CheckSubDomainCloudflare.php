<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\UserVendor;
use Illuminate\Support\Facades\DB;
class CheckSubDomainCloudflare extends Command
{
    /**
    * The name and signature of the console command.
    *
    * @var string
    */
    protected $signature = 'cron:check-and-delete-subdomain';
    
    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'Check And Delete SubDomain on Cloudflare.';
    
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
        $current_date = date('Y-m-d', strtotime("-1 month"));
        
        $shops = DB::table('user_vendors')->select('user_vendors.user_id as user_id', 'user_vendors.shop as shop', 'user_vendors.url as url', 'user_vendors.domain as domain')
        ->join('user_packages', 'user_vendors.user_id', '=', 'user_packages.user_id')
        // ->whereNull('user_vendors.domain')
        ->whereDate('user_packages.expired', '<=', $current_date)
        ->get();
        
        $this->info(count($shops));

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

        $cloudflare_destination_ip = config('app.cloudflare_destination_ip');
        $cloudflare_destination_url = config('app.cloudflare_destination_url');
        $remove_count = 0;

   
        if(isset($shops) && count($shops) > 0){
            foreach($shops as $key => $shop_item){ 
                try{
                    $zone = $client->get('zones?name=' .$cloudflare_destination_url. '&status=active&content='. $cloudflare_destination_ip)->getBody()->getContents();
                    $zone_response = json_decode($zone, true);
                    
                    $zone_id = $zone_response['result'][0]['id'];
                    $zone_name = $zone_response['result'][0]['name'];
                    
                }catch(Exception $e){
                    $this->error('ERROR : ' . $e->getResponse()->getBody()->getContents());
                }
                
                //get dns record with zone id
                try{
                    $dns = $client->get('zones/'. $zone_id . '/dns_records?name='. $shop_item->url . '.' . $zone_name . '&type=A&proxied=true')->getBody()->getContents();
                    $dns_response = json_decode($dns, true);

                    if($dns_response['success'] == true && !empty($dns_response['result'])){
                        try{
                            $del_dns = $client->delete('zones/' . $zone_id . '/dns_records/' . $dns_response['result'][0]['id']);
                            $del_dns_response = json_decode($del_dns->getBody()->getContents(), true);

                            if($del_dns_response['success'] == true){
                                $this->info(($key + 1) . '/' . count($shops) . ' ' . $shop_item->url . ' : ' . 'deleted');
                                $remove_count = $remove_count + 1;
                            }
                        }catch(Exception $e){
                            $this->error(($key + 1) . '/' . count($shops) . ' ' . $shop_item->url . ' : ' . 'ERROR : ' . $e->getResponse()->getBody()->getContents());
                        }
                    }else{
                        $this->warn(($key + 1) . '/' . count($shops) . ' ' . $shop_item->url . ' : ' . 'not found');
                    }

                    
                }catch(Exception $e){
                    $this->error('ERROR : ' . $e->getResponse()->getBody()->getContents());
                }
                
            }
        }
            
        $message = ''
            ."\n".'CRON : cron:check-and-delete-subdomain fine!'
            ."\n".'time :'.Carbon::now()
            ."\n".'remove '.$remove_count.' items!'
            ."\n".'from '.count($shops).' items!';

        sendLine(config('app.line_token_dev'), $message);

    }
}
        
