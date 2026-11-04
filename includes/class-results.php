<?php
/**
 * Results: Options ranked by Ballots, shared by the admin screen, the CSV
 * export and the Member-facing display.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Results {

	/** At or above this share of the leader's Ballots the bar is green. */
	const BAND_HIGH = 75;

	/** At or above this (and below BAND_HIGH) the bar is amber, otherwise red. */
	const BAND_MID = 40;

	/**
	 * Ranked rows. Ties share a rank and are ordered by name.
	 *
	 * @param int  $vote_id        Vote post ID.
	 * @param bool $include_hidden Include hidden Options (admin only).
	 * @return array<int,array{id:string,name:string,hidden:bool,count:int,pct_voters:float,pct_leader:float,band:string,rank:int}>
	 */
	public static function rows( $vote_id, $include_hidden = true ) {
		$counts = Nera_Vote_Votes::counts( $vote_id );
		$voters = Nera_Vote_Votes::voter_count( $vote_id );
		$list   = $include_hidden ? Nera_Vote_CPT::options( $vote_id ) : Nera_Vote_CPT::visible_options( $vote_id );

		$rows = array();
		foreach ( $list as $opt ) {
			$rows[] = array(
				'id'     => $opt['id'],
				'name'   => $opt['name'],
				'hidden' => $opt['hidden'],
				'count'  => isset( $counts[ $opt['id'] ] ) ? (int) $counts[ $opt['id'] ] : 0,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$leader = $rows ? $rows[0]['count'] : 0;
		$rank   = 0;
		$prev   = null;
		foreach ( $rows as $i => $row ) {
			if ( $row['count'] !== $prev ) {
				$rank = $i + 1;
				$prev = $row['count'];
			}
			$pct_leader               = $leader > 0 ? ( $row['count'] / $leader ) * 100 : 0;
			$rows[ $i ]['rank']       = $rank;
			$rows[ $i ]['pct_leader'] = round( $pct_leader, 1 );
			$rows[ $i ]['pct_voters'] = $voters > 0 ? round( ( $row['count'] / $voters ) * 100, 1 ) : 0.0;
			$rows[ $i ]['band']       = self::band( $pct_leader );
		}
		return $rows;
	}

	public static function band( $pct_of_leader ) {
		if ( $pct_of_leader >= self::BAND_HIGH ) {
			return 'high';
		}
		if ( $pct_of_leader >= self::BAND_MID ) {
			return 'mid';
		}
		return 'low';
	}

	/** Bar markup shared by admin and front end. */
	public static function bar_html( array $row ) {
		return sprintf(
			'<span class="nera-vote-bar" role="presentation"><span class="nera-vote-bar__fill nera-vote-bar__fill--%1$s" style="width:%2$s%%"></span></span>',
			esc_attr( $row['band'] ),
			esc_attr( (string) $row['pct_leader'] )
		);
	}
}
