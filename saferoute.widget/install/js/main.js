$(function () {
	if (SAFEROUTE_WIDGET === false)
		return console.error('SafeRoute: Не задан токен SafeRoute или ID магазина.');

	var lang = (function () {
		switch (SAFEROUTE_WIDGET.LANG) {
			case 'ru': return {
				selectDelivery: 'Выбрать способ доставки',
				changeDelivery: 'Изменить способ доставки',
				details: 'Детали',
				deliveryNotSelected: 'Способ доставки не выбран'
			};
			case 'en': return {
				selectDelivery: 'Select delivery',
				changeDelivery: 'Change delivery',
				details: 'Details',
				deliveryNotSelected: 'Delivery not selected'
			};
		}
	})();


	function sessionRequest (data) {
		$.post(SAFEROUTE_WIDGET.SESSION_SCRIPT, data, function () {
			// Обновление блоков на странице
			BX.Sale.OrderAjaxComponent.sendRequest();
		});
	}


	// Попап для виджета
	$('body').append(
		'<div id="sr-widget-wrap">' +
		'<div id="sr-widget-close">x</div>' +
		'<div id="sr-widget"></div>' +
		'</div>'
	);


	// Проверка, что открыта страница оформления заказа
	if ($('#bx-soa-order').length) {
		// Сброс текущей выбранной доставки SafeRoute
		sessionRequest({ action: 'reset_delivery' });
	}


	// Текущий выбранный и подтвержденный способ доставки из виджета
	var selectedDelivery;


	// Проверяет, что был выбран способ доставки с помощью SafeRoute
	function safeRouteIsSelected () {
		return ($('input[name=DELIVERY_ID]:checked').val() === SAFEROUTE_DELIVERY_ID);
	}

	// Рендерит информацию о выбранном способе доставки, а также кнопку открытия виджета для выбора
	function displayDeliveryInfo (to) {
		setTimeout(function () {
			// Если выбрано не SafeRoute, удалить блок с информацией и кнопкой
			if (!safeRouteIsSelected())
				return $('#sr-delivery-info').remove();

			if (!$('#sr-delivery-info').length) {
				$('#bx-soa-delivery .bx-soa-pp .bx-soa-pp-desc-container .bx-soa-pp-company')
					.append('<div id="sr-delivery-info"></div>');
			}

			var html = '';

			if (selectedDelivery) {
				html += '<ul class="bx-soa-pp-list"><li>';
				html += '<div class="bx-soa-pp-list-termin">' + lang.details + ':</div>';
				html += '<div class="bx-soa-pp-list-description">';
				html += selectedDelivery._meta.commonDeliveryData;
				html += '</div>';
				html += '</li></ul>';
			} else {
				html += '<p><b>' + lang.deliveryNotSelected + '</b></p>';
			}

			html += '<div id="sr-select-delivery-btn" class="btn btn-primary btn-md">' + (selectedDelivery ? lang.changeDelivery : lang.selectDelivery) + '</div>';

			$('#sr-delivery-info').html(html);
		}, to || 0);
	}
	displayDeliveryInfo(200);


	var widget = {
		instance: null,

		open: function () {
			this.close();

			var delivery;

			this.instance = new SafeRouteCartWidget('sr-widget', {
				mod: 'bitrix',
				currency: SAFEROUTE_WIDGET.CURRENCY ? SAFEROUTE_WIDGET.CURRENCY.toLowerCase() : undefined,
				regionName: $('input.bx-ui-sls-route').val(),
				apiScript: SAFEROUTE_WIDGET.API_SCRIPT,
				products: SAFEROUTE_WIDGET.PRODUCTS,
				weight: SAFEROUTE_WIDGET.WEIGHT,
				lang: SAFEROUTE_WIDGET.LANG
			});

			this.instance.on('change', function (data) { delivery = data; });
			this.instance.on('error', function (errors) { alert(errors); });

			this.instance.on('done', function (response) {
				selectedDelivery = delivery;
				// Отображение данных о выбранной доставке
				displayDeliveryInfo();

				var c = selectedDelivery.contacts;

				// Сохранение данных о доставке в сессии
				sessionRequest({
					action: 'set_delivery',

					saferoute_price: selectedDelivery.delivery.totalPrice + (selectedDelivery.payTypeCommission || 0),
					saferoute_order_id: response.id || 'no',
					saferoute_order_in_cabinet: response.confirmed ? 1 : 0,

					saferoute_full_name: c.fullName,
					saferoute_phone: c.phone,
					saferoute_city: selectedDelivery.city.name,
					saferoute_address: selectedDelivery._meta.fullDeliveryAddress,
					saferoute_index: c.address.zipCode
				});

				// Обновление данных в полях формы оформления на странице
				// Поля физ. лица
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.FIO)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.FIO).val(c.fullName);
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ADDRESS)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ADDRESS).val(selectedDelivery._meta.fullDeliveryAddress);
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.CITY)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.CITY).val(selectedDelivery.city.name);
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.PHONE)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.PHONE).val(c.phone);
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.EMAIL)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.EMAIL).val(c.email);
				if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ZIP)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ZIP).val(c.address.zipCode);
				// Поля юр. лица
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ADDRESS)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ADDRESS).val(selectedDelivery._meta.fullDeliveryAddress);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CITY)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CITY).val(selectedDelivery.city.name);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.COMPANY)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.COMPANY).val(c.companyName);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CONTACT_PERSON)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CONTACT_PERSON).val(c.fullName);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.EMAIL)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.EMAIL).val(c.email);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.PHONE)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.PHONE).val(c.phone);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.INN)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.INN).val(c.companyTIN);
				if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ZIP)
					$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ZIP).val(c.address.zipCode);

				// Закрытие виджета
				widget.close();
			});

			$('#sr-widget-wrap').addClass('visible');
		},
		close: function () {
			$('#sr-widget-wrap').removeClass('visible');
			if (this.instance) this.instance.destruct();
			this.instance = null;
		}
	};


	// Завершение обновления блоков на странице оформления заказа
	BX.addCustomEvent('onAjaxSuccess', function() {
		displayDeliveryInfo();
	});


	// Костыль, необходимый, потому что событие change для чекбоксов не срабатывает в случае
	// клика по самому блоку (с логотипом), в котором находится чекбокс
	$(document).on('click', '.bx-soa-pp-company-graf-container', function (e) {
		if (!$(e.target).is(':checkbox')) {
			$(this).find('input[name=DELIVERY_ID]').trigger('change');
		}
	});

	// Отслеживание изменения выбранного способа доставки
	$(document).on('change', 'input[name=DELIVERY_ID]', function () {
		if (safeRouteIsSelected() && !selectedDelivery) {
			widget.open();
		} else {
			widget.close();
		}

		displayDeliveryInfo();
	});

	// Закрытие кнопкой закрытия в углу
	$('#sr-widget-wrap #sr-widget-close').on('click', function () {
		widget.close();
	});

	// Закрытие по ESC
	$(window).on('keypress', function (e) {
		if (e.keyCode === 27) widget.close();
	});

	// Клик по кнопке выбора способа доставки
	$('#bx-soa-delivery').on('click', '#sr-select-delivery-btn', function () {
		widget.open();
	});

	// Клик по любому месту блока оформления заказа
	$(document).on('mouseup', '#bx-soa-order', function () {
		displayDeliveryInfo(200);
	});

	// Отправка формы оформления заказа
	$('#bx-soa-order-form').on('submit', function (e) {
		// Если доставкой выбрана SafeRoute, но в виджете способ доставки не выбран
		if (safeRouteIsSelected() && !selectedDelivery) {
			// Остановка отправки формы
			BX.PreventDefault(e);
			// Отображение виджета
			widget.open();

			BX.Sale.OrderAjaxComponent.endLoader();
			setTimeout(function(){
				BX.Sale.OrderAjaxComponent.endLoader();
			}, 100);
		}
	});
});