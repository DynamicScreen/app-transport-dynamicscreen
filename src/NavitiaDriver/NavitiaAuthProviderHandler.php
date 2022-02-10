<?php


namespace DynamicScreen\Transport\NavitiaDriver;

use DynamicScreen\SdkPhp\Handlers\TokenAuthProviderHandler;
use DynamicScreen\SdkPhp\Interfaces\IModule;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NavitiaAuthProviderHandler extends TokenAuthProviderHandler
{
    protected static string $provider = 'navitia';

    const TIMEOUT_DURATION = 60;

    const FR_COVERAGES = [
        'fr-idf' => "France - Ile de France",
        'fr-nw' => "France - Nord-Ouest",
        'fr-ne' => "France - Nord-Est",
        'fr-se' => "France - Sud-Est",
        'fr-sw' => "France - Sud-Ouest",
        'fr-ne-amiens' => "France - Keolis Amiens",
        'fr-nw-caen' => "France - Keolis Caen",
    ];

    public function __construct(IModule $module, $config = null)
    {
        parent::__construct($module, $config);
    }

    public function getDefaultOptions(): array
    {
        return [
            "api_key" => config("services.{$this->getProviderIdentifier()}.api_key"),
        ];
    }

    public function getTokenName(): string
    {
        return 'api_key';
    }

    public function provideData($setting = [])
    {
        $region = Arr::get($setting, 'region');
        $network = Arr::get($setting, 'network');
        $stop_area = Arr::get($setting, 'stop_area');

        $this->addData('stop_areas', fn () => $this->stop_areas($region, $network));
        $this->addData('stops', fn () => $this->autocompleteLines($region, $network, $stop_area));
        $this->addData('networks', fn () => $this->networksByCoverage());
//        $this->addData('page', fn () => $this->getPage(Arr::get($settings, 'pageId')));
    }

    public function testConnection($request)
    {
        $status = ['success' => true];

        return $status;
    }

    public function endpoint_uri(): string
    {
        return "https://api.navitia.io/v1";
    }

    public function onlyFr()
    {
        return Arr::get($this->default_config, 'fr_only', true);
    }

    public function get($uri, Array $options = [])
    {
        $headers = [
            'headers' => [ 'Authorization' =>  $this->default_config[$this->getTokenName()] ]
        ];
        $options = array_merge($headers, $options);

        $client = new Client();
        $response = $client->get($this->endpoint_uri() . Str::start($uri, '/'), $options);

//        try {
//            activity('navitia debug')->withProperties([
//                'uri'   => $uri,
//                'token' => $this->default_config['token']
//            ])->log("Call");
//        } catch (\Exception $ex) {
//
//        }

        return json_decode($response->getBody()->getContents());
    }

    public function getStopAreaInfo()
    {
        $client = new Client();
        $now = Carbon::now('Europe/Paris')->toDateTimeString();
        $now = str_replace('-','',$now);
        $now = str_replace(':','',$now);
        $now = str_replace(' ','T',$now);

        $response = $client->get($this->default_config['uri'] . 'coverage/fr-sw/stop_areas/stop_area%3AOBO%3ASA%3AEXPOSI/stop_schedules?items_per_schedule=3&from_datetime=' . $now, [
            'headers' => [
                'Authorization'     => 'e93690c0-c42d-45b5-ac40-d46753e2e6ec'
            ],
            'timeout' => self::TIMEOUT_DURATION
        ]);
        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        return $result;
    }

    public function getSecondStopAreaInfo()
    {
        $client = new Client();
        $now = Carbon::now('Europe/Paris')->toDateTimeString();
        $now = str_replace('-','',$now);
        $now = str_replace(':','',$now);
        $now = str_replace(' ','T',$now);

        $response = $client->get($this->default_config['uri'] . 'coverage/fr-sw/stop_areas/stop_area%3AOBX%3ASA%3ATEXPO/stop_schedules?items_per_schedule=3&from_datetime=' . $now, [
            'headers' => [
                'Authorization'     => 'e93690c0-c42d-45b5-ac40-d46753e2e6ec'
            ],
            'timeout' => self::TIMEOUT_DURATION
        ]);
        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        return $result;
    }

    public function coverages()
    {
        return collect(Cache::remember('dynamicscreen.navitia::coveragesList', Carbon::now()->addMonth(), function () {
            $result = $this->get('coverage', ['timeout' => self::TIMEOUT_DURATION]);
            return collect($result->regions)->reject(function ($region) {
                return !$region->name;
            })->pluck('name','id')->toArray();
        }));
    }

    public function coverageAt($lat, $lng)
    {
        return Cache::remember('dynamicscreen.navitia::coverages', Carbon::now()->addMonth(), function () use ($lat, $lng) {
            $result = $this->get("coord/{$lng};{$lat}", ['timeout' => self::TIMEOUT_DURATION]);
            return collect($result->regions)->first();
        });
    }

    public function networks($region)
    {
        return collect(Cache::remember('dynamicscreen.navitia::networks:' . $region, Carbon::now()->addWeeks(2), function () use ($region) {
            $result = $this->get("coverage/{$region}/networks?count=500", ['timeout' => self::TIMEOUT_DURATION]);
            return collect($result->networks)->pluck('name', 'id')->toArray();
        }));
    }

    public function networksByCoverage()
    {
        if ($this->onlyFr()) {
            $regions = self::FR_COVERAGES;
        } else {
            $regions = $this->coverages();
        }

        return Cache::remember('dynamicscreen.navitia::networksByCoverage:' . $this->onlyFr() ? 'fr' : 'all', Carbon::now()->addWeeks(2), function () use ($regions) {
            return collect($regions)->mapWithKeys(function ($region_name, $region) {
                return [$region_name => $this->networks($region)->mapWithKeys(function ($value, $key) use ($region) {
                    return [$region . ';' . $key => $value];
                })];
            })->toArray();
        });


    }

    public static function physicalMode($physical_mode)
    {
        switch ($physical_mode) {
            case 'physical_mode:Air':
                return 'plane';
            case 'physical_mode:Boat':
            case 'physical_mode:Ferry':
                return 'ship';
            case 'physical_mode:LocalTrain':
            case 'physical_mode:LongDistanceTrain':
            case 'physical_mode:RapidTransit':
            case 'physical_mode:Shuttle':
            case 'physical_mode:Train':
                return 'train';
            case 'physical_mode:Tramway':
            case 'physical_mode:Funicular':
                return 'tramway';
            case 'physical_mode:Metro':
            case 'physical_mode:RailShuttle':
                return 'subway';
            case 'physical_mode:Taxi':
                return 'taxi';
            case 'physical_mode:Bus':
            case 'physical_mode:BusRapidTransit':
            case 'physical_mode:Coach':
            default:
                return 'bus';
        }
    }


    public static function physicalModeToIcon($physical_mode)
    {
        switch ($physical_mode) {
            case 'physical_mode:Air':
                return 'fa-plane';
            case 'physical_mode:Boat':
            case 'physical_mode:Ferry':
                return 'fa-ship';
            case 'physical_mode:Funicular':
            case 'physical_mode:LocalTrain':
            case 'physical_mode:LongDistanceTrain':
            case 'physical_mode:RapidTransit':
            case 'physical_mode:Shuttle':
            case 'physical_mode:Train':
            case 'physical_mode:Tramway':
                return 'fa-train';
            case 'physical_mode:Metro':
            case 'physical_mode:RailShuttle':
                return 'fa-subway';
            case 'physical_mode:Taxi':
                return 'fa-taxi';
            case 'physical_mode:Bus':
            case 'physical_mode:BusRapidTransit':
            case 'physical_mode:Coach':
            default:
                return 'fa-bus';
        }
    }

    public static function shape($line_object)
    {
        $mode = collect($line_object->physical_modes)->first()->id;
        $network = $line_object->network->id;
        if ($mode == 'physical_mode:Tramway') {
            if (in_array($network, [
                'network:tbc', 'network:TAG', 'network:transpole', 'network:rtm', 'network:TAM', 'network:RTP',
            ])) {
                return 'circle';
            }

            if (in_array($network, [
                'network:Busdelaglo',
                'network:bibus',
                'network:bibus',
            ])) {
                return 'rounded';
            }
        } else if ($mode == 'physical_mode:Metro') {
            if (in_array($network, [
                'network:tcl', 'network:tisseo'
            ])) {
                return 'square';
            }
            return 'circle';
        }
        return 'square';
    }

    public static function colorType($line_object)
    {
        return 'fill';
    }

    public function stop_areas($region, $network)
    {
        return collect(Cache::remember("dynamicscreen.navitia::stop_areas:{$region}, {$network}", Carbon::now()->addWeek(), function () use ($region, $network) {
            $stop_areas = collect();
            $start_page = 0;

            do {
                $result = $this->get("coverage/{$region}/networks/{$network}/stop_areas?count=1000&start_page={$start_page}&depth=2", ['timeout' => self::TIMEOUT_DURATION]);
                $stop_areas = $stop_areas->concat($result->stop_areas);
                $start_page++;
            } while ($result->pagination->total_result > $start_page * $result->pagination->items_per_page);

            return $stop_areas->map(function ($stop_area) {
                $stop_area->icon = self::physicalModeToIcon(collect($stop_area->physical_modes)->first()->id);
                return $stop_area;
            });
        }));
    }

    public function routesAtStopArea($region, $network, $stop_area)
    {
        return collect(Cache::remember("dynamicscreen.navitia::routesAtStopArea:{$region}, {$network}, {$stop_area}", Carbon::now()->addWeek(), function () use ($region, $network, $stop_area) {
            if ($network === null) {
                $result = $this->get("coverage/{$region}/stop_areas/{$stop_area}/routes?depth=2", ['timeout' => self::TIMEOUT_DURATION]);
            } else {
                $result = $this->get("coverage/{$region}/networks/{$network}/stop_areas/{$stop_area}/routes?depth=2", ['timeout' => self::TIMEOUT_DURATION]);
            }
            return $result->routes;
        }));
    }

    public function route($region, $network, $route)
    {
        return Cache::remember("dynamicscreen.navitia::route:{$region}, {$network}, {$route}", Carbon::now()->addWeek(), function () use ($region, $network, $route) {
            $result = $this->get("coverage/{$region}/networks/{$network}/routes/{$route}?depth=2", ['timeout' => self::TIMEOUT_DURATION]);
            return collect($result->routes)->first();
        });
    }

    public function stop_area($region, $network, $stop_area)
    {
        return Cache::remember("dynamicscreen.naviti::stop_area:{$region}, {$network}, {$stop_area}", Carbon::now()->addWeek(), function () use ($region, $network, $stop_area) {
            if (is_array($region)) {
                $region = $region[1] . ';' . $region[0];
            }

            $result = $this->get("coverage/{$region}/networks/{$network}/stop_areas/{$stop_area}?depth=2", ['timeout' => self::TIMEOUT_DURATION]);
            return collect($result->stop_areas)->first();
        });
    }

    public function schedulesForRoute($region, $network, $stop_area, $route, $nb_schedules = 5)
    {
        $cacheDurationInMinutes = 60;
        $now = Carbon::now();

        $schedules = collect(Cache::remember("dynamicscreen.navitia::schedulesForRoute:{$region}, {$network}, {$stop_area}, {$route}, {$nb_schedules}", $now->addMinutes($cacheDurationInMinutes), function () use ($cacheDurationInMinutes, $now, $region, $network, $stop_area, $route, $nb_schedules) {
            //$nb_items = $nb_schedules * 20;
            $from = $now->toIso8601ZuluString();
            $duration = ($cacheDurationInMinutes + 15) * 60;

            if ($network === null) {
                $result = $this->get("coverage/{$region}/routes/{$route}/stop_areas/{$stop_area}/stop_schedules?from_datatime={$from}&duration={$duration}&depth=2&data_freshness=realtime&disable_geojson=true");
            } else {
                $result = $this->get("coverage/{$region}/networks/{$network}/routes/{$route}/stop_areas/{$stop_area}/stop_schedules?from_datatime={$from}&duration={$duration}&depth=2&data_freshness=realtime");
            }
            $schedules = collect($result->stop_schedules)->first();

            return collect($schedules->date_times)->map(function ($time) use ($schedules, $result) {
                $time->has_terminus = collect($time->links)->contains('category', 'terminus');
                if ($time->has_terminus) {
                    $dest_id = collect($time->links)->where('category', 'terminus')->first()->id;
                    $time->destination = collect($result->notes)->where('id', $dest_id)->first()->value;
                } else {
                    $time->destination = $schedules->route->direction->stop_area->name;
                }
                return $time;
            })->toArray();
        }));

        return $schedules->filter(function ($stop) {
            return Carbon::parse($stop->date_time)->isFuture();
        })->take($nb_schedules)->values();
    }

    public function stopAreasNearby($lat, $lng, $distance = 500, $max = 20)
    {
        return collect(Cache::remember("dynamicscreen.navitia::stopAreasNearby:{$lat}, {$lng}, {$distance}, {$max}", Carbon::now()->addWeeks(2), function () use ($lat, $lng, $distance, $max) {
            $result = $this->get("coords/{$lng};{$lat}/places_nearby?distance={$distance}&type[]=stop_area&count={$max}", ['timeout' => self::TIMEOUT_DURATION]);
            if(empty($result->places_nearby)){
                return [];
            }
            return collect($result->places_nearby)->pluck('stop_area.name', 'id')->toArray();
        }));
    }

    public function autocompleteLines($region, $network, $stop_area)
    {
        $lines = $this->routesAtStopArea($region, $network, $stop_area);

        return $lines->keyBy('id')->map(function ($route) {
            return [
                'label' => $route->direction->stop_area->name,
                'line' => [
                    'id' => $route->line->id,
                    'code' => $route->line->code,
                    'name' => $route->line->name,
                    'color' => $route->line->color,
                    'text_color' => $route->line->text_color,
                    'opening_time' => $route->line->opening_time,
                    'closing_time' => $route->line->closing_time,
                    'mode' => collect($route->line->physical_modes)->first()->name,
                    'icon' => self::physicalModeToIcon(collect($route->line->physical_modes)->first()->id)
                ],
            ];
        });
    }
}
