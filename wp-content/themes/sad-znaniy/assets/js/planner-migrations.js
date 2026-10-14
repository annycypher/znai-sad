/**
 * Миграции схемы плана планировщика участка (Этап 6, Партия 1).
 *
 * Сейчас схема в версии 1 — цепочка пуста. При несовместимом изменении схемы
 * добавляем функцию миграции и шаг в ORDER, как в _design/planner-spec.md §1:
 * ORDER = [ [ 1, 2, function( plan ){ / * правки * / return plan; } ] ].
 */
( function () {
	'use strict';

	var CURRENT = 1;
	var ORDER = [];

	window.szPlanMigrations = {
		current: CURRENT,
		run: function ( plan ) {
			var out = plan;
			ORDER.forEach( function ( step ) {
				if ( out && Number( out.version ) === step[ 0 ] ) {
					out = step[ 2 ]( out );
					out.version = step[ 1 ];
				}
			} );
			return out;
		},
	};
}() );
