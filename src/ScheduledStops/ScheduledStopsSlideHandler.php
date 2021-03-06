<?php

namespace DynamicScreen\Transport\ScheduledStops;

use DynamicScreen\SdkPhp\Handlers\SlideHandler;
use DynamicScreen\SdkPhp\Interfaces\ISlide;
use Illuminate\Support\Arr;

class ScheduledStopsSlideHandler extends SlideHandler
{

    public function fetch(ISlide $slide, $display = null): void
    {
        $navitia = $this->getAuthProvider($slide->getAccounts());

        $options = (object)$slide->getOptions();
//        dd($navitia, $options);

        if ($navitia == null) {
            return ;
        }

        if ($options->type == 'auto') {
//            if (!$display->lat || !$display->lng) {
//                return;
//            }
            $lat = Arr::get($display, 'lat', "43.6047");
            $lng = Arr::get($display, 'lng', "1.4442");


            $stops = $navitia->stopAreasNearby($lat, $lng, $slide->getOption('distance', 500), $slide->getOption('max_stops', 3));

            $stops->keys()->each(function ($stop_id) use ($slide, $lng, $lat, $navitia) {
                $region_id = $lng . ';' . $lat;
                $routes = $navitia->routesAtStopArea($region_id, null, $stop_id)->map(function ($route) {
                    return ['line' => $route->id];
                });

                $this->slideStopArea($slide, $navitia, $region_id, null, $stop_id, $routes);
            });
        } elseif ($options->type == 'fixed') {
            $parts = explode(';', $options->network_id);
            $region_id = $parts[0];
            $network_id = $parts[1];
            $stop_area_id = $options->stop_area_id;

            $this->slideStopArea($slide, $navitia, $region_id, $network_id, $stop_area_id, $slide->getOption('stops'));

        }
    }

    public function slideStopArea(ISlide $slide, $navitia, $region_id, $network_id, $stop_area_id, $stops)
    {
        $stop_area = $navitia->stop_area($region_id, $network_id, $stop_area_id);

        $data = [
            'stop'   => [
                'name' => $stop_area->name,
                'icon' => $navitia::physicalModeToIcon(collect($stop_area->physical_modes)->first()->id),
            ],
            'routes' => collect($stops)->map(function ($stop) use ($slide, $stop_area_id, $network_id, $region_id, $navitia) {
//                dd($stop, $network_id, $region_id);
                try {
                    $route = $navitia->route($region_id, $network_id, $stop['line']);

                } catch (\Exception $e) {
                    return [];
                }

//                $schedules = $navitia->schedulesForRoute($region_id, $network_id, $stop_area_id, $stop['line'], $slide->getOption('nb_schedules', 3));
                $schedules = $navitia->schedulesForRoute($region_id, $network_id, $stop_area_id, $stop['line'], $slide->getOption('nb_schedules', 3));
                $terminuses = $navitia->schedulesTerminusesForRoute($region_id, $network_id, $stop_area_id, $stop['line'], $slide->getOption('nb_schedules', 3));

                $type = $navitia::colorType($route->line);

                return ['route'                   => [
                    'code'       => $route->line->code,
                    'name'       => $route->line->name,
                    'terminus'   => $route->direction->stop_area->name,
                    'color'      => '#' . $route->line->color,
                    'text_color' => '#' . $route->line->text_color,
                    'shape'      => $navitia::shape($route->line),
                    'color_type' => $type,
                    'mode'       => $navitia::physicalMode(collect($route->line->physical_modes)->first()->id),
                ],
                    'has_multiple_terminuses' => $schedules->contains('has_terminus', true),
                    'nb_schedules'            => $slide->getOption('nb_schedules', 3),
                    'schedules'               => $schedules->map(function ($schedule) {
                        return [
                            'date_time'      => $schedule->date_time,
                            'data_freshness' => $schedule->data_freshness,
                            'has_terminus'   => $schedule->has_terminus,
                            'destination'    => $schedule->destination,
                        ];
                    }),
                    'terminuses'             => $terminuses->map(function ($schedules) {
                        return $schedules->map(function ($schedule) {
                                    return [
                                        'date_time'      => $schedule->date_time,
                                        'data_freshness' => $schedule->data_freshness,
                                        'has_terminus'   => $schedule->has_terminus,
                                        'destination'    => $schedule->destination,
                                    ];
                                });
                    })
                ];
            })->toArray()
        ];

        $this->addSlide($data);
    }
}
