$(function () {
	if(SAFEROUTE_WIDGET === false)
		return console.error('SafeRoute: Не задан API-ключ.');
	
	var lang = (function () {
		switch (SAFEROUTE_WIDGET.LANG) {
			case 'ru': return {
				selectDelivery: 'Выбрать способ доставки',
				changeDelivery: 'Изменить способ доставки',
				details: 'Детали',
				courier: 'Курьерская доставка',
				pickup: 'Самовывоз',
				post: 'Почта России',
				deliveryNotSelected: 'Способ доставки не выбран'
			};
			case 'en': return {
				selectDelivery: 'Select delivery',
				changeDelivery: 'Change delivery',
				details: 'Details',
				courier: 'Couriers delivery',
				pickup: 'Pickup',
				post: 'Post of Russia',
				deliveryNotSelected: 'Delivery not selected'
			};
			case 'zh': return {
				selectDelivery: '',
				changeDelivery: '',
				details: '',
				courier: '',
				pickup: '',
				post: '',
				deliveryNotSelected: ''
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
				var type = Number(selectedDelivery.delivery.type);
				
				html += '<ul class="bx-soa-pp-list"><li>';
				html += '<div class="bx-soa-pp-list-termin">' + lang.details + ':</div>';
				html += '<div class="bx-soa-pp-list-description">';
				
				if (type === 1) html += lang.pickup + ' (' + selectedDelivery.delivery.point.address + ')';
				else if (type === 2) html += lang.courier;
				else if (type === 3) html += lang.post;
				
				if (type !== 3) {
					html += ', ' + (selectedDelivery.delivery.point ? selectedDelivery.delivery.point.delivery_company_abbr : selectedDelivery.delivery.delivery_company_abbr);
				}
				
				html += ', ' + (selectedDelivery.delivery.point ? selectedDelivery.delivery.point.delivery_date : selectedDelivery.delivery.delivery_date);
				
				html += '</div>';
				html += '</li></ul>';
			} else {
				html += '<p><b>' + lang.deliveryNotSelected + '</b></p>';
			}
			
			html += '<div id="sr-select-delivery-btn" class="btn btn-default btn-md">' + (selectedDelivery ? lang.changeDelivery : lang.selectDelivery) + '</div>';
			
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
				apiScript: SAFEROUTE_WIDGET.API_SCRIPT,
				products: SAFEROUTE_WIDGET.PRODUCTS,
				weight: SAFEROUTE_WIDGET.WEIGHT,
				lang: SAFEROUTE_WIDGET.LANG,
				mod: 'bitrix'
			});
			
			this.instance.on('change', function (data) {
				delivery = data;
			});
			
			this.instance.on('error', function (err) { console.error(err); });
			
			this.instance.on('afterSubmit', function (response) {
				if (response.status === 'ok') {
					selectedDelivery = delivery;
					// Отображение данных о выбранной доставке
					displayDeliveryInfo();
					
					var c = selectedDelivery.contacts;
					var address = (selectedDelivery.delivery.point)
						? selectedDelivery.delivery.point.address
						: (c.address.street + ', ' + c.address.house + ', ' + c.address.flat);
					
					// Сохранение данных о доставке в сессии
					sessionRequest({
						action: 'set_delivery',
						
						saferoute_price: selectedDelivery.delivery.point
							? selectedDelivery.delivery.point.price_delivery
							: selectedDelivery.delivery.total_price,
						saferoute_order_id: response.id || 'no',
						saferoute_order_in_cabinet: response.confirmed ? 1 : 0,
						
						saferoute_full_name: c.fullName,
						saferoute_phone: c.phone,
						saferoute_city: selectedDelivery.city.name,
						saferoute_address: address,
						saferoute_index: c.address.index
					});
					
					// Обновление данных в полях формы оформления на странице
					if (ORDER_PROPS_FOR_SAFEROUTE.FIO)
						$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.FIO).val(c.fullName);
					if (ORDER_PROPS_FOR_SAFEROUTE.ADDRESS)
						$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.ADDRESS).val(address);
					if (ORDER_PROPS_FOR_SAFEROUTE.CITY)
						$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.CITY).val(selectedDelivery.city.name);
					if (ORDER_PROPS_FOR_SAFEROUTE.PHONE)
						$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.PHONE).val(c.phone);
					if (ORDER_PROPS_FOR_SAFEROUTE.ZIP)
						$('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.ZIP).val(c.address.index);
					
					// Закрытие виджета
					widget.close();
				} else {
					alert('SafeRoute Error');
					console.error(response.message);
				}
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