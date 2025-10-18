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
                
                // FIRST: Extract URLs from any existing anchor tags before stripping
                $desc = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>.*?<\/a>/i', '$1', $desc);
                
                // Strip ALL tags except <br>
                $desc = strip_tags( $desc, '<br>' );
                
                // Clean up "Name <email>" format - extract just the email
                $desc = preg_replace( '/[^<]*<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/', '$1', $desc );
                
                // Store Zoom URLs with placeholders BEFORE make_clickable to avoid double-wrapping
                $zoom_links = [];
                $desc = preg_replace_callback(
                        '/(https?:\/\/[^\s<>]*zoom\.us[^\s<>]*)/i',
                        function($matches) use (&$zoom_links) {
                                $index = count($zoom_links);
                                $zoom_links[$index] = $matches[1];
                                return '___ZOOM_LINK_' . $index . '___';
                        },
                        $desc
                );
                
                // Convert plain email addresses and other URLs to links
                $desc = make_clickable( $desc );
                
                // Replace placeholders with actual Zoom Link anchors
                foreach ($zoom_links as $index => $url) {
                        $desc = str_replace(
                                '___ZOOM_LINK_' . $index . '___',
                                '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">Zoom Link</a>',
                                $desc
                        );
                }
                
                // Add target="_blank" to any remaining links that don't have it
                $desc = preg_replace('/<a\s+href=(["\'])(?![^>]*target=)/i', '<a target="_blank" rel="noopener noreferrer" href=$1', $desc);
                
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
                        foreach ( $events[ $key ] as $ev ): 
                                // For mobile, show FULL description with clickable links
                                // Replace Zoom URLs in location with clickable "Zoom Link"
                                $mob_location = '';
                                if ( !empty($ev['location']) ) {
                                        if ( preg_match('/zoom\.us/i', $ev['location']) ) {
                                                $mob_location = '<a href="' . esc_url($ev['location']) . '" target="_blank" rel="noopener noreferrer">Zoom Link</a>';
                                        } else {
                                                $mob_location = esc_html($ev['location']);
                                        }
                                }
                        ?>
                                <div class="mob-event">
                                        <div class="mob-bar"><?= esc_html( $ev['time'] . ' ' . $ev['title'] ) ?></div>
                                        <div class="mob-body">
                                                <?php if ( $mob_location ) echo '<div>' . $mob_location . '</div>'; ?>
                                                <?php if ( $ev['notes_html'] ) echo '<div>' . $ev['notes_html'] . '</div>'; ?>
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
                                                <?php foreach ( $events[ $key ] ?? [] as $ev ): 
                                                        // Create preview text (first 2 lines only)
                                                        // Convert <br> tags to newlines BEFORE stripping tags
                                                        $preview_html = str_replace(['<br>', '<br/>', '<br />'], "\n", $ev['notes_html']);
                                                        $plain_notes = wp_strip_all_tags( $preview_html );
                                                        
                                                        // Replace ALL Zoom URLs with "Zoom Link"
                                                        $plain_notes = preg_replace(
                                                                '/https?:\/\/[^\s]*zoom\.us[^\s]*/i',
                                                                'Zoom Link',
                                                                $plain_notes
                                                        );
                                                        
                                                        // Remove multiple consecutive newlines (no empty lines)
                                                        $plain_notes = preg_replace('/\n\n+/', "\n", $plain_notes);
                                                        
                                                        // Remove leading and trailing whitespace/newlines
                                                        $plain_notes = trim( $plain_notes );
                                                        
                                                        // Show only first 2 lines for preview (max ~100 chars total)
                                                        $has_notes = !empty( $plain_notes );
                                                        $preview = '';
                                                        if ( $has_notes ) {
                                                                $lines = explode("\n", $plain_notes);
                                                                
                                                                // Take first 2 lines, but limit total length to ~100 chars
                                                                $first_line = isset($lines[0]) ? $lines[0] : '';
                                                                $second_line = isset($lines[1]) ? $lines[1] : '';
                                                                
                                                                // Build preview with character limit
                                                                if (mb_strlen($first_line) > 50) {
                                                                        $preview = mb_substr($first_line, 0, 50) . '...';
                                                                } else {
                                                                        $preview = $first_line;
                                                                        if (!empty($second_line)) {
                                                                                $remaining = 100 - mb_strlen($first_line);
                                                                                if (mb_strlen($second_line) > $remaining) {
                                                                                        $preview .= "\n" . mb_substr($second_line, 0, $remaining) . '...';
                                                                                } else {
                                                                                        $preview .= "\n" . $second_line;
                                                                                        if (count($lines) > 2) {
                                                                                                $preview .= '...';
                                                                                        }
                                                                                }
                                                                        }
                                                                }
                                                        }
                                                ?>
                                                        <div class="cc-event cc-event-clickable" 
                                                             data-time="<?= esc_attr( $ev['time'] ) ?>"
                                                             data-title="<?= esc_attr( $ev['title'] ) ?>"
                                                             data-location="<?= esc_attr( $ev['location'] ) ?>"
                                                             data-notes="<?= esc_attr( $plain_notes ) ?>"
                                                             data-notes-html="<?= esc_attr( $ev['notes_html'] ) ?>">
                                                                <div class="cc-event-first">
                                                                        <span class="cc-time"><?= esc_html( $ev['time'] ) ?></span>
                                                                        <span class="cc-title"><?= esc_html( $ev['title'] ) ?></span>
                                                                </div>
                                                                <?php if ( $has_notes ): ?>
                                                                        <div class="cc-event-preview"><?= esc_html( $preview ) ?> <span class="cc-read-more">[Read More]</span></div>
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

<!-- Event Modal Popup -->
<div id="cc-event-modal" class="cc-modal" style="display:none;">
        <div class="cc-modal-overlay"></div>
        <div class="cc-modal-content">
                <button class="cc-modal-close">&times;</button>
                <h2 class="cc-modal-title"></h2>
                <div class="cc-modal-time"></div>
                <div class="cc-modal-location"></div>
                <div class="cc-modal-description"></div>
        </div>
</div>

<style>
/* Modal Styles */
.cc-modal{position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;display:flex;align-items:center;justify-content:center;}
.cc-modal-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);cursor:pointer;}
.cc-modal-content{position:relative;background:#fff;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:1;}
.cc-modal-close{position:absolute;top:10px;right:15px;background:transparent;border:0;font-size:32px;color:#999;cursor:pointer;padding:0;width:30px;height:30px;line-height:30px;}
.cc-modal-close:hover{color:#333;}
.cc-modal-title{margin:0 0 15px;color:#5A2B80;font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;line-height:1.2;}
.cc-modal-time{font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:600;color:#574565;margin-bottom:10px;line-height:1.2;}
.cc-modal-location{font-family:'Barlow Condensed',sans-serif;font-size:1rem;color:#666;margin-bottom:15px;font-style:italic;line-height:1.2;}
.cc-modal-description{font-family:'Barlow Condensed',sans-serif;font-size:1rem;line-height:1.3;color:#333;margin-top:20px;padding-top:20px;border-top:1px solid #e5e5e5;}
.cc-modal-description a{color:#5A2B80;text-decoration:underline;}
.cc-modal-description a:hover{color:#6E4A81;}
/* Event preview with [Read More] */
.cc-event-preview{font-size:0.88rem;color:#555;line-height:1.3;margin-top:3px;padding:2px 4px 0;white-space:pre-line;font-weight:500;}
.cc-read-more{color:#5A2B80;font-weight:600;white-space:nowrap;}
/* Make desktop events clickable */
@media (min-width:801px){
  .cc-event-clickable{cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;}
  .cc-event-clickable:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(90,43,128,0.3);}
}
</style>

<?php
        /* auto-swap between modes <800 px / ≥801 px */
        echo '<script>(function(){const u=new URL(location);const mq=matchMedia("(max-width:800px)");function swap(){const wk=u.searchParams.get("view")==="week";if(mq.matches&&!wk){u.searchParams.set("view","week");u.searchParams.set("wk","0");location.replace(u);}if(!mq.matches&&wk){u.searchParams.delete("view");u.searchParams.delete("wk");location.replace(u);}}swap();mq.addEventListener?.("change",swap);})();</script>';
        
        /* Event modal popup script (desktop only) */
        echo '<script>
(function(){
  if(window.innerWidth<=800)return;
  const modal=document.getElementById("cc-event-modal");
  const overlay=modal.querySelector(".cc-modal-overlay");
  const close=modal.querySelector(".cc-modal-close");
  const title=modal.querySelector(".cc-modal-title");
  const time=modal.querySelector(".cc-modal-time");
  const location=modal.querySelector(".cc-modal-location");
  const description=modal.querySelector(".cc-modal-description");
  
  function showModal(ev){
    title.textContent=ev.dataset.title||"";
    time.textContent=ev.dataset.time||"";
    
    // Handle location - make Zoom URLs clickable as "Zoom Link"
    const loc=ev.dataset.location||"";
    if(loc){
      if(/zoom\.us/i.test(loc)){
        location.innerHTML=\'<a href="\'+loc+\'" target="_blank" rel="noopener noreferrer">Zoom Link</a>\';
      }else if(/^https?:\/\//i.test(loc)){
        location.innerHTML=\'<a href="\'+loc+\'" target="_blank" rel="noopener noreferrer">\'+loc+\'</a>\';
      }else{
        location.textContent=loc;
      }
      location.style.display="block";
    }else{
      location.style.display="none";
    }
    
    description.innerHTML=ev.dataset.notesHtml||"No additional details.";
    
    // Clean up mailto: links - show just the email address
    description.querySelectorAll("a[href^=\'mailto:\']").forEach(function(link){
      const email=link.href.replace(/^mailto:/i,"");
      link.textContent=email;
    });
    
    modal.style.display="flex";
    document.body.style.overflow="hidden";
  }
  
  function hideModal(){
    modal.style.display="none";
    document.body.style.overflow="";
  }
  
  document.querySelectorAll(".cc-event-clickable").forEach(function(ev){
    ev.addEventListener("click",function(){showModal(this);});
  });
  
  overlay.addEventListener("click",hideModal);
  close.addEventListener("click",hideModal);
  
  document.addEventListener("keydown",function(e){
    if(e.key==="Escape"&&modal.style.display==="flex")hideModal();
  });
})();
</script>';

        return ob_get_clean();
} );
