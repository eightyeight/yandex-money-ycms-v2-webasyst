<?php

namespace YaMoney\Request\Refunds;

use YaMoney\Common\AbstractRequestBuilder;
use YaMoney\Common\Exceptions\InvalidPropertyValueException;
use YaMoney\Common\Exceptions\InvalidPropertyValueTypeException;

/**
 * Класс билдера объектов запросов к API списка возвратов
 *
 * @package YaMoney\Request\Refunds
 */
class RefundsRequestBuilder extends AbstractRequestBuilder
{
    /**
     * @var RefundsRequest Инстанс собираемого объекта запроса
     */
    protected $currentObject;

    /**
     * Инициализирует новый инстанс собираемого объекта
     * @return RefundsRequest Инстанс собираемого запроса
     */
    protected function initCurrentObject()
    {
        return new RefundsRequest();
    }

    /**
     * Устанавливает идентификатор возврата
     * @param string $value Идентификатор возврата, который ищется в API
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Выбрасывается если длина переданного значения не равна 36
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setRefundId($value)
    {
        $this->currentObject->setRefundId($value);
        return $this;
    }

    /**
     * Устанавливает идентификатор платежа или null если требуется его удалить
     * @param string|null $value Идентификатор платежа
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Выбрасывается если длина переданной строки не равна 36 символам
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setPaymentId($value)
    {
        $this->currentObject->setPaymentId($value);
        return $this;
    }

    /**
     * Устанавлвиает идентификатор магазина
     * @param string $value Идентификатор магазина или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setAccountId($value)
    {
        $this->currentObject->setAccountId($value);
        return $this;
    }

    /**
     * Устанавливает идентификатор шлюза
     * @param string|null $value Идентификатор шлюза или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setGatewayId($value)
    {
        $this->currentObject->setGatewayId($value);
        return $this;
    }

    /**
     * Устанавливает статус выбираемых возвратов
     * @param string $value Статус выбираемых платежей или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Выбрасывается если переданное значение не является валидным статусом
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setStatus($value)
    {
        $this->currentObject->setStatus($value);
        return $this;
    }

    /**
     * Устанавливает токен следующей страницы выборки
     * @param string $value Токен следующей страницы выборки или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueTypeException Выбрасывается если в метод была передана не строка
     */
    public function setNextPage($value)
    {
        $this->currentObject->setNextPage($value);
        return $this;
    }

    /**
     * Устанавливает дату создания от которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время создания, от (не включая) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setCreatedGt($value)
    {
        $this->currentObject->setCreatedGt($value);
        return $this;
    }

    /**
     * Устанавливает дату создания от которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время создания, от (включительно) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setCreatedGte($value)
    {
        $this->currentObject->setCreatedGte($value);
        return $this;
    }

    /**
     * Устанавливает дату создания до которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время создания, до (не включая) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setCreatedLt($value)
    {
        $this->currentObject->setCreatedLt($value);
        return $this;
    }

    /**
     * Устанавливает дату создания до которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время создания, до (включительно) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setCreatedLte($value)
    {
        $this->currentObject->setCreatedLte($value);
        return $this;
    }

    /**
     * Устанавливает дату проведения от которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время проведения операции, от (не включая) или null чтобы удалить
     * значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setAuthorizedGt($value)
    {
        $this->currentObject->setAuthorizedGt($value);
        return $this;
    }

    /**
     * Устанавливает дату проведения от которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время проведения операции, от (не включая) или null чтобы удалить
     * значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setAuthorizedGte($value)
    {
        $this->currentObject->setAuthorizedGte($value);
        return $this;
    }

    /**
     * Устанавливает дату проведения до которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время проведения, до (не включая) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setAuthorizedLt($value)
    {
        $this->currentObject->setAuthorizedLt($value);
        return $this;
    }

    /**
     * Устанавливает дату проведения до которой выбираются возвраты
     * @param \DateTime|string|int|null $value Время проведения, до (включительно) или null чтобы удалить значение
     * @return RefundsRequestBuilder Инстанс текущего объекта билдера
     *
     * @throws InvalidPropertyValueException Генерируется если была передана дата в невалидном формате (была передана
     * строка или число, которые не удалось преобразовать в валидную дату)
     * @throws InvalidPropertyValueTypeException Генерируется если была передана дата с не тем типом (передана не
     * строка, не число и не значение типа \DateTime)
     */
    public function setAuthorizedLte($value)
    {
        $this->currentObject->setAuthorizedLte($value);
        return $this;
    }

    /**
     * Собирает и возвращает объект запроса списка возвратов магазина
     * @param array|null $options Массив с настройками запроса
     * @return RefundsRequestInterface Инстанс объекта запроса к API для получения списка возвратов магазина
     */
    public function build(array $options = null)
    {
        return parent::build($options);
    }
}