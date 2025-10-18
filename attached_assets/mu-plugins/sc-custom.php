<?php
/**
 * MU-Plugin: Simple Calendar inline times & descriptions
 */

// Prepend event start time
add_filter( 'simcal_event_html', function( $html, $event, $calendar ) {
    if ( ! empty( $event->start->dateTime ) ) {
        $dt = new DateTime( $event->start->dateTime );
        $dt->setTimezone( new DateTimeZone( $calendar->timezone ) );
        $time = $dt->format( 'g:ia' );
        return '<span class="simcal-event-time">' . esc_html( $time ) . '</span> ' . $html;
    }
    return $html;
}, 20, 3 );

// Append inline description
add_filter( 'simcal_event_html', function( $html, $event ) {
    if ( ! empty( $event->description ) ) {
        $html .= '<div class="simcal-event-description-inline">'
               . wp_kses_post( $event->description )
               . '</div>';
    }
    return $html;
}, 10, 2 );
