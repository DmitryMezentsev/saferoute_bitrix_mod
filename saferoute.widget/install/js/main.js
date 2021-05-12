$(function () {
  if (SAFEROUTE_WIDGET === false)
    return console.error('SafeRoute: Не задан токен SafeRoute или ID магазина.');

  var lang = (function () {
    switch (SAFEROUTE_WIDGET.LANG) {
      case 'ru': return {
        selectDelivery: 'Выбрать способ доставки',
        changeDelivery: 'Изменить способ доставки',
        details: 'Детали',
        deliveryNotSelected: 'Способ доставки не выбран',
        canNotCreateAnOrder: 'Не можем создать заказ',
        selectOtherDeliveryOrPayMethod: 'Выбранный способ доставки не допускает оплату при получении. Пожалуйста, выберите другой способ доставки в виджете или измените способ оплаты.'
      };
      case 'en': return {
        selectDelivery: 'Select delivery',
        changeDelivery: 'Change delivery',
        details: 'Details',
        deliveryNotSelected: 'Delivery not selected',
        canNotCreateAnOrder: 'Can not create an order',
        selectOtherDeliveryOrPayMethod: 'The selected delivery method does not accept payment upon receipt. Please choose a different shipping method in the widget or change the payment method.'
      };
    }
  })();


  function sessionRequest (data) {
    $.post(SAFEROUTE_WIDGET.SESSION_SCRIPT, data, function () {
      // Обновление блоков на странице
      BX.Sale.OrderAjaxComponent.sendRequest();
    });
  }


  // Для скрытия стоимости доставки SafeRoute, когда доставка в виджете не выбрана
  $('head').append(
    '<style type="text/css" rel="stylesheet">' +
    '#ID_DELIVERY_ID_' + SAFEROUTE_DELIVERY_ID + ' ~ .bx-soa-pp-delivery-cost.zero-price,' +
    '#ID_DELIVERY_ID_' + SAFEROUTE_COURIER_DELIVERY_ID + ' ~ .bx-soa-pp-delivery-cost.zero-price,' +
    '#ID_DELIVERY_ID_' + SAFEROUTE_PICKUP_DELIVERY_ID + ' ~ .bx-soa-pp-delivery-cost.zero-price ' +
    '{ display: none; }' +
    '</style>'
  );


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
    var val = $('input[name=DELIVERY_ID]:checked').val();

    return (
      (SAFEROUTE_DELIVERY_ID && val === SAFEROUTE_DELIVERY_ID) ||
      (SAFEROUTE_COURIER_DELIVERY_ID && val === SAFEROUTE_COURIER_DELIVERY_ID) ||
      (SAFEROUTE_PICKUP_DELIVERY_ID && val === SAFEROUTE_PICKUP_DELIVERY_ID)
    );
  }

  // Возвращает ID выбранного способа оплаты
  function getSelectedPaySystemID () {
    return Number($('input[name=PAY_SYSTEM_ID]:checked').val());
  }

  // Вернёт true, если выбрана оплата при получении, недопустимая при выбранном способе доставки
  function selectedIsImpossibleCODPaymentMethod () {
    if (!selectedDelivery) return false;

    var selectedPaymentMethod = getSelectedPaySystemID();
    var settings = selectedDelivery._meta.widgetSettings;

    if (!selectedPaymentMethod) return false;

    return (
      selectedPaymentMethod === Number(settings.payMethodWithCOD) ||
      selectedPaymentMethod === Number(settings.cardPayMethodWithCOD)
    ) && selectedDelivery.delivery.nppDisabled;
  }

  // Определяет, какого типа была была выбрана доставка SafeRoute
  function getSelectedDeliveryType() {
    switch ($('input[name=DELIVERY_ID]:checked').val()) {
      case SAFEROUTE_PICKUP_DELIVERY_ID: return 1;
      case SAFEROUTE_COURIER_DELIVERY_ID: return 2;
    }
  }

  // Возвращает итоговую стоимость доставки в зависимости от текущего выбранного способа оплаты
  function getDeliveryTotalPrice() {
    if (!selectedDelivery) return 0;

    var price = selectedDelivery.delivery.totalPrice;

    if (selectedDelivery.payTypeCommission)
      price += selectedDelivery.payTypeCommission;

    var payMethodWithCOD = Number(selectedDelivery._meta.widgetSettings.payMethodWithCOD);
    var cardPayMethodWithCOD = Number(selectedDelivery._meta.widgetSettings.cardPayMethodWithCOD);

    if (payMethodWithCOD && getSelectedPaySystemID() === payMethodWithCOD)
      price += selectedDelivery.delivery.priceCommissionCod || 0;
    else if (cardPayMethodWithCOD && getSelectedPaySystemID() === cardPayMethodWithCOD)
      price += selectedDelivery.delivery.priceCommissionCodCard || 0;

    return price;
  }

  // Возвращает ID соответствующего способа оплаты для выбранного способа оплаты в виджете
  function getPaymentMethodForAcquiring() {
    if (!selectedDelivery) return null;

    switch(selectedDelivery.payType) {
      case 1: return selectedDelivery._meta.widgetSettings.payMethodWithCOD; // Оплата при получении
      case 2: return SAFEROUTE_PAY_METHOD_ID; // Эквайринг
    }
  }

  // Выбирает указанный способ оплаты
  function setPaymentMethod(id) {
    if (id) {
      $('#bx-soa-paysystem .bx-soa-section-title-container').trigger('click'); // Если это говно не развернуть, переключение не сработает
      $('input#ID_PAY_SYSTEM_ID_' + id).closest('.bx-soa-pp-company').trigger('click');
    }
  }

  // Скрывает все способы оплаты, кроме переданного
  function hideOtherPaymentMethods(id) {
    if (id) $('input#ID_PAY_SYSTEM_ID_' + id).closest('.bx-soa-pp-company').siblings('.bx-soa-pp-company').hide();
  }

  // Отображает информацию о выбранном способе доставки, кнопку открытия виджета,
  // а также скрывает стоимость доставки, если способ в виджете не выбран
  function displayDeliveryInfo (to) {
    // Помечаем нулевые цены
    $('.bx-soa-pp-delivery-cost').each(function () {
      if (parseFloat($(this).text()) === 0)
        $(this).addClass('zero-price');
      else
        $(this).removeClass('zero-price');
    });

    setTimeout(function () {
      // Если выбрано не SafeRoute
      if (!safeRouteIsSelected()) {
        $('#sr-delivery-info').remove();
      } else {
        var $price = $('.bx-soa-pp-company .bx-soa-pp-list');
        var $freePriceRight = (function () {
          var $row = null;

          $('#bx-soa-total .bx-soa-price-free').closest('.bx-soa-cart-total-line').each(function () {
            if (/(доставка)|(deliver)|(ship)/i.test($(this).find('.bx-soa-cart-t').text()))
              $row = $(this);
          });

          return $row;
        })();

        if (parseFloat($('.bx-soa-pp-company .bx-soa-pp-list .bx-soa-pp-list-description').text()) === 0) {
          $price.hide();

          if ($freePriceRight) $freePriceRight.hide();
        } else {
          $price.show();

          if ($freePriceRight) $freePriceRight.show();
        }

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
      }
    }, to || 0);
  }
  displayDeliveryInfo(200);


  var widget = {
    instance: null,

    open: function () {
      this.close();

      var delivery;

      this.instance = new SafeRouteCartWidget('sr-widget', {
        devMode: SAFEROUTE_DEV_MODE,
        mod: 'bitrix',
        lang: SAFEROUTE_WIDGET.LANG,
        currency: SAFEROUTE_WIDGET.CURRENCY ? SAFEROUTE_WIDGET.CURRENCY.toLowerCase() : undefined,
        apiScript: SAFEROUTE_WIDGET.API_SCRIPT,
        disableMultiRequests: SAFEROUTE_WIDGET.DISABLE_MULTI_REQUESTS,
        lockPickupFilters: SAFEROUTE_WIDGET.LOCK_PICKUP_FILTERS,
        deliveryType: getSelectedDeliveryType(),

        products: SAFEROUTE_WIDGET.PRODUCTS,
        weight: SAFEROUTE_WIDGET.WEIGHT,

        regionName: $('input.bx-ui-sls-route').val(),
        userFullName: $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.FIO).val() || undefined,
        userPhone: $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.PHONE).val() || undefined,
        userEmail: $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.EMAIL).val() || undefined
      });

      this.instance.on('change', function (data) {
        delivery = data;
      });
      this.instance.on('error', function (errors) { alert(errors); });

      this.instance.on('done', function (response) {
        selectedDelivery = delivery;
        // Отображение данных о выбранной доставке
        displayDeliveryInfo();

        var c = selectedDelivery.contacts;

        // Сохранение данных о доставке в сессии
        sessionRequest({
          action: 'set_delivery',

          saferoute_price: getDeliveryTotalPrice(),
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
        if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.EMAIL && c.email)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.EMAIL).val(c.email);
        if (ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ZIP && c.address.zipCode)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.INDIVIDUAL.ZIP).val(c.address.zipCode);
        // Поля юр. лица
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ADDRESS)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ADDRESS).val(selectedDelivery._meta.fullDeliveryAddress);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CITY)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CITY).val(selectedDelivery.city.name);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.COMPANY && c.companyName)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.COMPANY).val(c.companyName);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CONTACT_PERSON)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.CONTACT_PERSON).val(c.fullName);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.EMAIL && c.email)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.EMAIL).val(c.email);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.PHONE)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.PHONE).val(c.phone);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.INN && c.companyTIN)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.INN).val(c.companyTIN);
        if (ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ZIP && c.address.zipCode)
          $('#soa-property-' + ORDER_PROPS_FOR_SAFEROUTE.LEGAL.ZIP).val(c.address.zipCode);

        // Закрытие виджета
        widget.close();
        // Если используется эквайринг через виджет, выбрать нужный способ оплаты
        setPaymentMethod(getPaymentMethodForAcquiring());
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
    // Если используется эквайринг через виджет, скрыть лишние способы оплаты
    hideOtherPaymentMethods(getPaymentMethodForAcquiring());
  });


  // Костыль, необходимый, потому что событие change для чекбоксов не срабатывает в случае
  // клика по самому блоку (с логотипом), в котором находится чекбокс
  $(document).on('click', '.bx-soa-pp-company-graf-container', function (e) {
    if (!$(e.target).is(':checkbox')) {
      $(this).find('input[name=DELIVERY_ID], input[name=PAY_SYSTEM_ID]').trigger('change');
    }
  });

  // Изменение выбранного способа доставки
  $(document).on('change', 'input[name=DELIVERY_ID]', function () {
    if (safeRouteIsSelected() && !selectedDelivery) {
      widget.open();
    } else {
      widget.close();
    }

    // Сброс выбранной в виджете доставки, если идёт разделение доставки SR по способам
    if (
      (SAFEROUTE_COURIER_DELIVERY_ID && SAFEROUTE_PICKUP_DELIVERY_ID) ||
      (SAFEROUTE_DELIVERY_ID && SAFEROUTE_PICKUP_DELIVERY_ID) ||
      (SAFEROUTE_COURIER_DELIVERY_ID && SAFEROUTE_DELIVERY_ID)
    ) {
      sessionRequest({ action: 'reset_delivery' });
      selectedDelivery = undefined;
    }

    displayDeliveryInfo();
  });

  // Изменение выбранного способа оплаты
  $(document).on('change', 'input[name=PAY_SYSTEM_ID]', function () {
    if (safeRouteIsSelected()) sessionRequest({ action: 'update_delivery_price', price: getDeliveryTotalPrice() });
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

  // Отправка формы оформления заказа (для старых версий битрикса)
  $('#bx-soa-order-form').on('submit', function (e) {
    if (safeRouteIsSelected()) {
      // Если в виджете способ доставки не выбран
      if (!selectedDelivery) {
        BX.PreventDefault(e);
        BX.Sale.OrderAjaxComponent.endLoader();
        setTimeout(function() { BX.Sale.OrderAjaxComponent.endLoader() }, 100);

        // Отображение виджета
        return widget.open();
      }

      // Если выбрана оплата при получении, недопустимая при выбранном способе доставки
      if (selectedIsImpossibleCODPaymentMethod()) {
        BX.PreventDefault(e);
        BX.Sale.OrderAjaxComponent.endLoader();
        setTimeout(function() { BX.Sale.OrderAjaxComponent.endLoader() }, 100);

        new BX.CDialog({
          title: lang.canNotCreateAnOrder,
          content: lang.selectOtherDeliveryOrPayMethod,
          height: '150',
          width: '360',
          resizable: false,
          draggable: false,
          buttons: [BX.CDialog.btnClose]
        }).Show();
      }
    }
  });

  // Отправка формы оформления заказа (для новых версий битрикса)
  BX.Event.EventEmitter.subscribe('BX.Sale.OrderAjaxComponent:onBeforeSendRequest', function (e) {
    var data = e.getData();

    if (data.action === 'saveOrderAjax' && safeRouteIsSelected()) {
      // Если в виджете способ доставки не выбран
      if (!selectedDelivery) {
        data.cancel = true;
        setTimeout(function() { BX.Sale.OrderAjaxComponent.endLoader() }, 100);

        // Отображение виджета
        return widget.open();
      }

      // Если выбрана оплата при получении, недопустимая при выбранном способе доставки
      if (selectedIsImpossibleCODPaymentMethod()) {
        data.cancel = true;
        setTimeout(function() { BX.Sale.OrderAjaxComponent.endLoader() }, 100);

        new BX.CDialog({
          title: lang.canNotCreateAnOrder,
          content: lang.selectOtherDeliveryOrPayMethod,
          height: '150',
          width: '360',
          resizable: false,
          draggable: false,
          buttons: [BX.CDialog.btnClose]
        }).Show();
      }
    }
  });
});