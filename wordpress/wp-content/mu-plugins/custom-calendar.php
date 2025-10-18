<?php
/**
 * Plugin Name:  Public Calendar Shortcode (API-Key Only)
 * Description : 7-column month grid on ≥ 801 px • rolling 7-day list on ≤ 800 px.
 *               Prev/Next nav, anchor-jump, “today” highlight.
 * Version     : 3.2.7 – robust timezone (always site / New-York time)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'custom_calendar', function ( $atts ) {

        /* ───── ATTRIBUTES ───────────────────────────────────── */
        $atts = shortcode_atts( [
                'api_key'     => '',
                'calendar_id' => '',
        ], $atts, 'custom_calendar' );

        if ( ! $atts['api_key'] || ! $atts['calendar_id'] ) {
                return '<p><strong>Error:</strong> shortcode needs <code>api_key</code> &amp; <code>calendar_id</code>.</p>';
        }

        $api_key     = sanitize_text_field( $atts['api_key'] );
        $calendar_id = sanitize_text_field( $atts['calendar_id'] );

        /* ───── VIEW PARAMS ─────────────────────────────────── */
        $view        = sanitize_key( $_GET['view'] ?? 'month' ); // 'month' | 'week'
        $week_offset = intval(      $_GET['wk']   ?? 0 );        // ±1, ±2 …

        /* ───── TIMEZONE  ─────────────────────────────────────
           wp_timezone(): returns DateTimeZone using either the named
           “Timezone” setting or the numeric GMT offset.  Fallback chain:
           wp_timezone_string() → America/New_York. */
        if ( function_exists( 'wp_timezone' ) ) {
                $tz = wp_timezone();
        } else {
                $tz_name = wp_timezone_string();
                $tz = new DateTimeZone( $tz_name ? $tz_name : 'America/New_York' );
        }

        /* today = “now” snapped to midnight in site zone */
        $today = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0, 0 );

        /* ───── DATE WINDOW & HEADER ────────────────────────── */
        if ( $view === 'week' ) {

                $start_local = $today->modify( $week_offset . ' weeks' ); // exact today ± n weeks
                $end_local   = $start_local->modify( '+6 days' );         // 7 items

                /* week-of-month label 1-4 */
                $firstOfMonth = (int) $start_local->format( 'j' );
                $wom          = min( 4, 1 + (int) floor( ($firstOfMonth - 1) / 7 ) );

                $header_lbl   = $start_local->format( 'F Y' ) . ' – Week ' . $wom;

        } else { /* month grid */

                $year  = isset( $_GET['cal_year'] )  ? intval( $_GET['cal_year'] )  : (int) date( 'Y' );
                $month = isset( $_GET['cal_month'] ) ? intval( $_GET['cal_month'] ) : (int) date( 'n' );

                try {
                        $start_local = new DateTimeImmutable(
                                sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz
                        );
                } catch ( Exception $e ) {
                        $start_local = new DateTimeImmutable( 'first day of this month', $tz );
                }

                $end_local  = $start_local->modify( '+1 month' );
                $header_lbl = $start_local->format( 'F Y' );
        }

        /* ───── GOOGLE API RANGE (UTC) ─────────────────────── */
        $timeMin = $start_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( DateTime::RFC3339 );
        $timeMax = $end_local  ->setTimezone( new DateTimeZone( 'UTC' ) )->format( DateTime::RFC3339 );

        /* ───── FETCH EVENTS ───────────────────────────────── */
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
               rawurlencode( $calendar_id ) . '/events?' .
               http_build_query( [
                       'key'          => $api_key,
                       'timeMin'      => $timeMin,
                       'timeMax'      => $timeMax,
                       'singleEvents' => 'true',
                       'orderBy'      => 'startTime',
                       'maxResults'   => 2500,
               ], '', '&', PHP_QUERY_RFC3986 );

        $resp = wp_remote_get( $url );
        if ( is_wp_error( $resp ) )
                return '<p><strong>Network error:</strong> ' . esc_html( $resp->get_error_message() ) . '</p>';

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! empty( $data['error']['message'] ) )
                return '<p><strong>Google API error:</strong> ' . esc_html( $data['error']['message'] ) . '</p>';

        /* ───── HELPER: Clean up description HTML ─────────── */
        $clean_description = function( $desc ) {
                if ( empty( $desc ) ) return '';
                
                // Strip out <b>, <u>, <strong>, <em>, <i> tags but keep content
                $desc = strip_tags( $desc, '<a><br>' );
                
                // Clean up "Name <email>" format - extract just the email
                $desc = preg_replace( '/[^<]*<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/', '$1', $desc );
                
                // Convert plain email addresses to mailto links
                $desc = make_clickable( $desc );
                
                // Replace Zoom URLs with "Zoom Link" text (after make_clickable creates the anchor tags)
                $desc = preg_replace(
                        '/<a href="(https?:\/\/[^"]*zoom\.us[^"]*)"[^>]*>.*?<\/a>/i',
                        '<a href="$1" target="_blank" rel="noopener noreferrer">Zoom Link</a>',
                        $desc
                );
                
                // Add target="_blank" to any remaining links
                $desc = str_replace( '<a href', '<a target="_blank" rel="noopener noreferrer" href', $desc );
                
                // Convert newlines to <br>
                $desc = nl2br( $desc );
                
                return $desc;
        };

        /* ───── GROUP EVENTS (YYYY-MM-DD ⇒ []) ─────────────── */
        $events = [];
        foreach ( $data['items'] ?? [] as $item ) {
                $start = $item['start']['dateTime'] ?? $item['start']['date'];
                $dt    = ( new DateTimeImmutable( $start ) )->setTimezone( $tz );
                $key   = $dt->format( 'Y-m-d' );

                $events[ $key ][] = [
                        'time'       => isset( $item['start']['dateTime'] ) ? $dt->format( 'g:ia' ) : 'All Day',
                        'title'      => sanitize_text_field( $item['summary']   ?? '' ),
                        'location'   => sanitize_text_field( $item['location']  ?? '' ),
                        'notes_html' => $clean_description( $item['description'] ?? '' ),
                ];
        }

        $today_key = $today->format( 'Y-m-d' );
        $page_url  = home_url( '/' );
        $anchor    = '#custom-calendar';

        /* ───── NAV LINKS ───────────────────────────────────── */
        if ( $view === 'week' ) {
                $prev_link = esc_url( add_query_arg( ['view'=>'week','wk'=>$week_offset-1], $page_url ) . $anchor );
                $next_link = esc_url( add_query_arg( ['view'=>'week','wk'=>$week_offset+1], $page_url ) . $anchor );
        } else {
                $year  = (int) $start_local->format( 'Y' );
                $month = (int) $start_local->format( 'n' );
                $prev_y=$year; $prev_m=$month-1; if($prev_m<1){$prev_m=12;$prev_y--;}
                $next_y=$year; $next_m=$month+1; if($next_m>12){$next_m=1;$next_y++;}
                $prev_link = esc_url( add_query_arg( ['cal_year'=>$prev_y,'cal_month'=>$prev_m], $page_url ) . $anchor );
                $next_link = esc_url( add_query_arg( ['cal_year'=>$next_y,'cal_month'=>$next_m], $page_url ) . $anchor );
        }

        /* ───── HTML OUTPUT ─────────────────────────────────── */
        ob_start(); ?>
<div id="custom-calendar" class="custom-calendar" data-view="<?= esc_attr( $view ) ?>">
        <div class="cc-month-year">
                <a href="<?= $prev_link ?>" class="cc-prev">&lt;</a>
                <?= $header_lbl ?>
                <a href="<?= $next_link ?>" class="cc-next">&gt;</a>
        </div>

<?php if ( $view === 'week' ): /* ░░ PHONE LIST ░░ */ ?>

        <?php for ( $i = 0; $i < 7; $i++ ):
                $day_dt = $start_local->modify( "+$i days" );
                $key    = $day_dt->format( 'Y-m-d' ); ?>
                <div class="mob-date-row<?= $key === $today_key ? ' today' : '' ?>">
                        <span class="mob-date-left">
                                <span class="mob-month"><?= $day_dt->format('F') ?></span>
                                <span class="mob-num"><?= $day_dt->format('j') ?></span>
                        </span>
                        <span class="mob-date-right"><?= $day_dt->format( 'l' ) ?></span>
                </div>

                <?php if ( ! empty( $events[ $key ] ) ):
                        foreach ( $events[ $key ] as $ev ): ?>
                                <div class="mob-event">
                                        <div class="mob-bar"><?= esc_html( $ev['time'] . ' ' . $ev['title'] ) ?></div>
                                        <div class="mob-body">
                                                <?php if ( $ev['location'] )   echo '<div>' . esc_html( $ev['location'] ) . '</div>'; ?>
                                                <?php if ( $ev['notes_html'] ) echo '<div>' . $ev['notes_html']         . '</div>'; ?>
                                        </div>
                                </div>
                <?php endforeach; else: ?>
                        <div class="mob-nope"></div>
                <?php endif;
        endfor; ?>

        <!-- bottom nav bar -->
        <div class="cc-month-year">
                <a href="<?= $prev_link ?>" class="cc-prev">&lt;</a>
                <?= $header_lbl ?>
                <a href="<?= $next_link ?>" class="cc-next">&gt;</a>
        </div>

<?php else: /* ░░ DESKTOP MONTH GRID ░░ */ ?>

        <div class="cc-row cc-weekday-header">
                <?php foreach ( ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd ): ?>
                        <div class="cc-cell cc-weekday"><?= $wd ?></div>
                <?php endforeach; ?>
        </div>

        <?php
        $first_dow = (int) $start_local->format( 'w' );
        $cells     = array_merge( array_fill( 0, $first_dow, '' ),
                                  range( 1, (int) $start_local->format( 't' ) ) );
        while ( count( $cells ) % 7 ) $cells[] = '';

        foreach ( array_chunk( $cells, 7 ) as $week ): ?>
                <div class="cc-row cc-week">
                        <?php foreach ( $week as $day ):
                                $key = $day ? $start_local->format( 'Y-m' ) . '-' . sprintf( '%02d', $day ) : ''; ?>
                                <div class="cc-cell<?= $day ? '' : ' empty' ?>">
                                        <?php if ( $day ): ?>
                                                <div class="cc-day-header">
                                                        <span class="cc-day-num<?= $key === $today_key ? ' today' : '' ?>"><?= $day ?></span>
                                                </div>
                                                <?php foreach ( $events[ $key ] ?? [] as $ev ): ?>
                                                        <div class="cc-event">
                                                                <div class="cc-event-first">
                                                                        <span class="cc-time"><?= esc_html( $ev['time'] ) ?></span>
                                                                        <strong class="cc-title"><?= esc_html( $ev['title'] ) ?></strong>
                                                                </div>
                                                                <?php if ( $ev['location'] ): ?>
                                                                        <div class="cc-event-second"><?= esc_html( $ev['location'] ) ?></div>
                                                                <?php endif; ?>
                                                                <?php if ( $ev['notes_html'] ): ?>
                                                                        <div class="cc-event-notes"><?= $ev['notes_html'] ?></div>
                                                                <?php endif; ?>
                                                        </div>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </div>
                        <?php endforeach; ?>
                </div>
        <?php endforeach; ?>

<?php endif; /* view */ ?>

        <?php if ( empty( $events ) ) echo '<p class="cc-none">No events found.</p>'; ?>
</div>

<?php
        /* auto-swap between modes <800 px / ≥801 px */
        echo '<script>(function(){const u=new URL(location);const mq=matchMedia("(max-width:800px)");function swap(){const wk=u.searchParams.get("view")==="week";if(mq.matches&&!wk){u.searchParams.set("view","week");u.searchParams.set("wk","0");location.replace(u);}if(!mq.matches&&wk){u.searchParams.delete("view");u.searchParams.delete("wk");location.replace(u);}}swap();mq.addEventListener?.("change",swap);})();</script>';

        return ob_get_clean();
} );
