<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChartsService {
	public function __construct( private MonthlyIncomesRepository $incomes_repository, private TransactionsRepository $transactions_repository, private DatabaseManager $database_manager ) {}

	public function dashboard_charts( string $month_key ): array {
		$income_vs_common = $this->income_vs_common( $month_key, $month_key );
		$budget_vs_actual = $this->common_budget_vs_actual( $month_key );

		return array(
			'income_vs_split_vs_common' => array(
				'labels' => array_map( static fn( array $row ): string => (string) $row['month_key'], $income_vs_common['series'] ),
				'income' => array_map( static fn( array $row ): float => (float) $row['income_total'], $income_vs_common['series'] ),
				'common_budget' => array_map( static fn( array $row ): float => (float) $row['common_budget'], $income_vs_common['series'] ),
				'actual_expense' => array_map( static fn( array $row ): float => (float) $row['actual_expense'], $income_vs_common['series'] ),
			),
			'common_expense_by_category' => $this->common_expense_by_category( $month_key ),
			'budget_vs_expense' => array(
				'budget' => (float) $budget_vs_actual['common_budget'],
				'expense' => (float) $budget_vs_actual['actual_expense'],
			),
			'common_budget_evolution' => $this->common_budget_trend( gmdate( 'Y-m', strtotime( '-11 months', strtotime( $month_key . '-01' ) ) ), $month_key ),
		);
	}

	public function income_vs_common( string $from, string $to ): array {
		$series = array();
		foreach ( $this->build_month_keys( $from, $to ) as $month_key ) {
			$income_totals      = $this->incomes_repository->totals_for_month( $month_key );
			$transaction_totals = $this->transactions_repository->month_totals( $month_key );
			$budget_actual      = $this->common_budget_vs_actual( $month_key );
			$cash_in_total      = (float) $transaction_totals['cash_in_total'] > 0 ? (float) $transaction_totals['cash_in_total'] : (float) $income_totals['total'];
			$series[] = array(
				'month_key'       => $month_key,
				'income_total'    => $cash_in_total,
				'separated_total' => (float) $budget_actual['separated_total'],
				'common_budget'   => (float) $budget_actual['common_budget'],
				'actual_expense'  => (float) $budget_actual['actual_expense'],
			);
		}

		return array( 'series' => $series );
	}

	public function common_expense_by_category( string $month ): array {
		global $wpdb;
		$transactions_table = $this->database_manager->table( 'transactions' );
		$categories_table   = $this->database_manager->table( 'categories' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT c.id AS category_id, c.name AS category_name, COALESCE(SUM(t.amount),0) AS total FROM {$categories_table} c LEFT JOIN {$transactions_table} t ON t.category_id = c.id AND t.month_key = %s AND t.type = 'expense' GROUP BY c.id, c.name ORDER BY total DESC", $month ), ARRAY_A );
	}

	public function common_budget_vs_actual( string $month ): array {
		global $wpdb;
		$alloc_table = $this->database_manager->table( 'monthly_allocations' );
		$tx_table    = $this->database_manager->table( 'transactions' );
		$allocation         = $wpdb->get_row( $wpdb->prepare( "SELECT common_budget, separated_total FROM {$alloc_table} WHERE month_key = %s", $month ), ARRAY_A );
		$income_totals      = $this->incomes_repository->totals_for_month( $month );
		$transaction_totals = $this->transactions_repository->month_totals( $month );
		$cash_in_total      = (float) $transaction_totals['cash_in_total'] > 0 ? (float) $transaction_totals['cash_in_total'] : (float) $income_totals['total'];
		$expense            = (float) $transaction_totals['expense_total'];

		return array(
			'month_key'       => $month,
			'common_budget'   => $allocation ? (float) $allocation['common_budget'] : $cash_in_total,
			'separated_total' => $allocation ? (float) $allocation['separated_total'] : 0.0,
			'actual_expense'  => $expense,
		);
	}

	public function common_budget_trend( string $from, string $to ): array {
		$rows = array();
		foreach ( $this->build_month_keys( $from, $to ) as $month_key ) {
			$data   = $this->common_budget_vs_actual( $month_key );
			$rows[] = array(
				'month_key'       => $month_key,
				'common_budget'   => (float) $data['common_budget'],
				'separated_total' => (float) $data['separated_total'],
				'actual_expense'  => (float) $data['actual_expense'],
			);
		}

		return $rows;
	}

	private function build_month_keys( string $from, string $to ): array {
		$from_ts = strtotime( preg_match( '/^\d{4}-\d{2}$/', $from ) ? $from . '-01' : gmdate( 'Y-m-01' ) );
		$to_ts   = strtotime( preg_match( '/^\d{4}-\d{2}$/', $to ) ? $to . '-01' : gmdate( 'Y-m-01' ) );

		if ( false === $from_ts || false === $to_ts ) {
			return array( gmdate( 'Y-m' ) );
		}
		if ( $from_ts > $to_ts ) {
			[ $from_ts, $to_ts ] = array( $to_ts, $from_ts );
		}

		$months = array();
		$current = $from_ts;
		while ( $current <= $to_ts ) {
			$months[] = gmdate( 'Y-m', $current );
			$current  = strtotime( '+1 month', $current );
		}

		return $months ?: array( gmdate( 'Y-m' ) );
	}
}
