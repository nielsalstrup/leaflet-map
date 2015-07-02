<?php

    $place_flag = false;
    $route_flag = false;

    $defaults = array_merge($this::$defaults['text'], $this::$defaults['checks']);

    /* defaults from db */
    $default_zoom = get_option('leaflet_default_zoom', $defaults['leaflet_default_zoom']);
    $default_zoom_control = get_option('leaflet_show_zoom_controls', $defaults['leaflet_show_zoom_controls']);
    $default_height = get_option('leaflet_default_height', $defaults['leaflet_default_height']);
    $default_width = get_option('leaflet_default_width', $defaults['leaflet_default_width']);
    $default_show_attr = get_option('leaflet_show_attribution', $defaults['leaflet_show_attribution']);
    $default_tileurl = get_option('leaflet_map_tile_url', $defaults['leaflet_map_tile_url']);
    $default_subdomains = get_option('leaflet_map_tile_url_subdomains', $defaults['leaflet_map_tile_url_subdomains']);
    $default_scrollwheel = get_option('leaflet_scroll_wheel_zoom', $defaults['leaflet_scroll_wheel_zoom']);


    if (empty($the_query)) {
        if (empty($the_place)) {
            if (empty($the_route)) {
                $points = mp_query();
            } else {
                $route_flag = true;

                $color = empty($color) ? "black" : $color;
                $fitline = empty($fitline) ? 0 : $fitline;

                $draggable = empty($draggable) ? 'false' : $draggable;
                $visible = empty($visible) ? false : ($visible == 'true');
                $transporttype = empty($the_route->transporttype) ? 'motorcar' : $transporttype;
                $instructions = '0';

                $locations = Array();

                $iconA = plugins_url( 'images/a_dd0000.png', __FILE__ );
                $iconB = plugins_url( 'images/b_00dd00.png', __FILE__ );

                if (!empty($the_route->addresses)) {
                    $addresses = preg_split('/\s?[;|\/]\s?/', $addresses);
                    foreach ($addresses as $address) {
                        if (trim($address)) {
                            $geocoded = $this::osm_geocode($address);
                            $locations[] = Array($geocoded->{'lat'}, $geocoded->{'lng'});
                        }
                    }
                } else if (!empty($the_route->latlngs)) {
                    $latlngs = preg_split('/\s?[;|\/]\s?/', $latlngs);
                    foreach ($latlngs as $latlng) {
                        if (trim($latlng)) {
                            $locations[] = array_map('floatval', preg_split('/\s?,\s?/', $latlng));
                        }
                    }
                } else if (!empty($the_route->coordinates)) {
                    $coordinates = preg_split('/\s?[;|\/]\s?/', $coordinates);
                    foreach ($coordinates as $xy) {
                        if (trim($xy)) {
                            $locations[] = array_map('floatval', preg_split('/\s?,\s?/', $xy));
                        }
                    }
                }

                $route = $this::yours_routing( $locations[0], $locations[1], $transporttype, $instructions );
            }
        } else {
            $place_flag = true;
            $locations[0] = $this::osm_geocode($the_place->address);
            $prad = $the_place->rad;
            $plat = $locations[0][0];
            $plng = $locations[0][1];                        
        }

        //$points = $the_query;
    }
    $points = !empty($the_query) ? $the_query : mp_query();
    $height = '500';
    $width = '100%';

    $lat = empty($lat) ? '55.675283' : $lat;
    $lng = empty($lng) ? '12.570163' : $lng;


    /* check more user defined $atts against defaults */
    $tileurl = empty($tileurl) ? $default_tileurl : $tileurl;
    $show_attr = empty($show_attr) ? $default_show_attr : $show_attr;
    $subdomains = empty($subdomains) ? $default_subdomains : $subdomains;
    $zoomcontrol = empty($zoomcontrol) ? $default_zoom_control : $zoomcontrol;
    $zoom = empty($zoom) ? $default_zoom : $zoom;
    $scrollwheel = empty($scrollwheel) ? $default_scrollwheel : $scrollwheel;
    $height = empty($height) ? $default_height : $height;
    $width = empty($width) ? $default_width : $width;

    /* check more user defined $atts against defaults */
    $height = empty($height) ? $default_height : $height;
    $width = empty($width) ? $default_width : $width;
    $zoomcontrol = empty($zoomcontrol) ? $default_zoom_control : $zoomcontrol;
    $zoom = empty($zoom) ? 1 : $zoom;
    $scrollwheel = empty($scrollwheel) ? $default_scrollwheel : $scrollwheel;
    
    /* allow percent, but add px for ints */
    $height .= is_numeric($height) ? 'px' : '';
    $width .= is_numeric($width) ? 'px' : '';   
    
    $content = '<div id="leaflet-wordpress-map-0" class="leaflet-wordpress-map" style="height:'.$height.'; width:'.$width.';"></div>';
    $content .= "<script>

        var map,
            baseURL = '{$tileurl}',
            base = L.tileLayer(baseURL, { 
               subdomains: '{$subdomains}'
            });
       
            map = L.map('leaflet-wordpress-map-0', 
              {
                  layers: [base],
                  zoomControl: 1,
                  scrollWheelZoom: 0
              }).setView([55.546002, 11.7463939], 8);";

    if ($show_attr) {
        /* add attribution to MapQuest, OSM and Map Icons*/
        $content .= '
            map.attributionControl.addAttribution("© <a href=\"http://www.openstreetmap.org/\">OpenStreetMap</a>");

            map.attributionControl.addAttribution("Tiles by <a href=\"http://www.mapquest.com/\" target=\"_blank\">MapQuest</a> <img src=\"http://developer.mapquest.com/content/osm/mq_logo.png\" />");

            map.attributionControl.addAttribution("Markers by <a href=\"https:/mapicons.mapsmarker.com/\">Map Icons</a>");';
    }
    
    $content .= "

        var cluster,
            draggable = false;

            cluster = L.markerClusterGroup({
              maxClusterRadius: 60,
              iconCreateFunction: null,
              spiderfyOnMaxZoom: true,
              showCoverageOnHover: false,
              zoomToBoundsOnClick: true,
              disableClusteringAtZoom: 17
            });";
    

    foreach ($points as $point) {

        $title = htmlspecialchars( $point['name'] );
        $info = '<h5><a href="' . $point['url'] . '">' . $point['name'] . '</a></h5>';
        if ( !empty($point['type']) && !empty($point['ownership']) ) {
            $info .= $point['ownership']->name . ' ' . strtolower($point['type']->name);
        }
        $content .= "
            homeIcon = new L.icon({ iconUrl: '{$point['icon']}', iconSize: [32, 37], iconAnchor: [16, 36] });
            marker = new L.marker(new L.LatLng({$point['lat']}, {$point['lng']}), { icon: homeIcon } );
            marker.bindPopup('$info');
        ";

        $content .= "
            cluster.addLayer( marker );
        ";

    }

    $content .= "
        map.addLayer( cluster );
        map.fitBounds( cluster.getBounds() );";

    if ($route_flag) {
        $content .= "
            iconA = L.icon({iconUrl: '{$iconA}', iconSize: [32, 37], iconAnchor: [16, 36]});
            iconB = L.icon({iconUrl: '{$iconB}', iconSize: [32, 37], iconAnchor: [16, 36]});
            markerA = L.marker(new L.LatLng({$flat}, {$flng}), {icon: iconA});
            markerB = L.marker(new L.LatLng({$tlat}, {$tlng}), {icon: iconB});
        ";

        $content .="
            routePoints = [
        ";

        foreach ($route as $waypoint) {
            $content .= "new L.LatLng({$waypoint[1]}, {$waypoint[0]})";
            if ($waypoint!==end($route))
                $content .= ",";
            $content .="
            ";
        }

        $content .="];
        ";

        $content .="
            routeOptions = {
                color: 'blue',
                weight: 4,
                opacity: 0.5
            };
        ";

        $content .="
            route = L.polyline( routePoints, routeOptions );
        ";


        $content .="

            markerA.addTo( map );
            markerB.addTo( map );
            
            previous_map.addLayer( route );
            previous_map.fitBounds( route.getBounds() );
        ";
    }

    $content .= "

    </script>";

    echo $content;
?>
