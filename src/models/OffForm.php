<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Class OffForm
 * @package app\models
 */
class OffForm extends Model
{
    /**
     * @var int
     */
    public $startYear;

    /**
     * @var int
     */
    public $startMonth;

    /**
     * @var int
     */
    public $startDay;

    /**
     * @var int
     */
    public $endYear;

    /**
     * @var int
     */
    public $endMonth;

    /**
     * @var int
     */
    public $endDay;

    /**
     * @var string
     */
    public $note;

    private $off;

    /**
     * OffForm constructor.
     * @param Off $off
     * @param array $config
     * @throws \yii\base\InvalidConfigException
     */
    public function __construct(Off $off, array $config = [])
    {
        $this->off = $off;

        $this->startYear = Yii::$app->formatter->asDate($off->start_at, 'y');
        $this->startMonth = Yii::$app->formatter->asDate($off->start_at, 'M');
        $this->startDay = Yii::$app->formatter->asDate($off->start_at, 'd');
        $this->endYear = Yii::$app->formatter->asTime($off->end_at, 'y');
        $this->endMonth = Yii::$app->formatter->asTime($off->end_at, 'M');
        $this->endDay = Yii::$app->formatter->asDate($off->end_at, 'd');
        $this->note = !empty($off->note) ? $off->note : null;

        parent::__construct($config);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['startYear', 'startMonth', 'startDay', 'endYear', 'endMonth', 'endDay'], 'required'],
            [['startYear', 'endYear'], 'number', 'min' => 2018],
            [['startMonth', 'endMonth'], 'number', 'min' => 1, 'max' => 12],
            [['startDay', 'endDay'], 'number', 'min' => 1, 'max' => 31],
            [['startDay', 'endDay'], 'maxDay'],
            [['startDay'], 'verifyStart'],
            [['endDay'], 'verifyEnd'],
            [['endDay'], 'verifyBetween'],
            [['note'], 'string'],
        ];
    }

    /**
     * @param int|string $year
     * @param int|string $month
     * @param int|string $day
     * @param int|string $hour
     * @param int|string $minute
     * @return string
     */
    public function prepareDate($year, $month, $day, $hour, $minute): string
    {
        return $year
            . '-'
            . ($month < 10 ? '0' : '')
            . $month
            . '-'
            . ($day < 10 ? '0' : '')
            . $day
            . ' '
            . ($hour < 10 ? '0' : '')
            . $hour
            . ':'
            . ($minute < 10 ? '0' : '')
            . $minute
            . ':00';
    }

    /**
     * @param int|string $year
     * @param int|string $month
     * @param int|string $day
     * @param int|string $hour
     * @param int|string $minute
     * @return int
     * @throws \Exception
     */
    public function prepareTimestamp($year, $month, $day, $hour, $minute): int
    {
        return (new \DateTime(
            $this->prepareDate($year, $month, $day, $hour, $minute),
            new \DateTimeZone(Yii::$app->timeZone)
        ))->getTimestamp();
    }

    /**
     * @param string $attribute
     * @throws \Exception
     */
    public function maxDay(string $attribute): void
    {
        if ($attribute === 'startDay') {
            $year = $this->startYear;
            $month = $this->startMonth;
        } else {
            $year = $this->endYear;
            $month = $this->endMonth;
        }

        $maxDaysInMonth = date(
            't',
            $this->prepareTimestamp($year, $month, 1, 1, 0)
        );

        if ($this->$attribute > $maxDaysInMonth) {
            $this->addError($attribute, Yii::t('app', 'Selected month has got only {max} days.', ['max' => $maxDaysInMonth]));
        }
    }

    /**
     * @throws \Exception
     */
    public function verifyStart(): void
    {
        if (!$this->hasErrors()) {
            $conditions = [
                'and',
                ['user_id' => Yii::$app->user->id],
                ['<=', 'start_at', $this->prepareTimestamp($this->startYear, $this->startMonth, $this->startDay, 0, 0)],
                ['>=', 'end_at', $this->prepareTimestamp($this->startYear, $this->startMonth, $this->startDay, 0, 0)],
            ];

            if ($this->off->id !== null) {
                $conditions[] = ['<>', 'id', $this->off->id];
            }

            if (Off::find()->where($conditions)->exists()) {
                $this->addError('startDay', Yii::t('app', 'Selected day overlaps another off-time.'));
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function verifyEnd(): void
    {
        if ($this->prepareTimestamp($this->startYear, $this->startMonth, $this->startDay, 0, 0)
            >= $this->prepareTimestamp($this->endYear, $this->endMonth, $this->endDay, 23, 59)) {
            $this->addError('endDay', Yii::t('app', 'Off-time ending day can not be earlier than starting day.'));
        } elseif (!$this->hasErrors()) {
            $conditions = [
                'and',
                ['user_id' => Yii::$app->user->id],
                ['<=', 'start_at', (int) Yii::$app->formatter->asTimestamp($this->prepareDate($this->endYear, $this->endMonth, $this->endDay, 23, 59))],
                ['>=', 'end_at', (int) Yii::$app->formatter->asTimestamp($this->prepareDate($this->endYear, $this->endMonth, $this->endDay, 23, 59))],
            ];

            if ($this->off->id !== null) {
                $conditions[] = ['<>', 'id', $this->off->id];
            }

            if (Off::find()->where($conditions)->exists()) {
                $this->addError('endDay', Yii::t('app', 'Selected day overlaps another off-time.'));
            }
        }
    }

    public function verifyBetween(): void
    {
        if (!$this->hasErrors()) {
            $conditions = [
                'and',
                ['user_id' => Yii::$app->user->id],
                ['>=', 'start_at', (int) Yii::$app->formatter->asTimestamp($this->prepareDate($this->startYear, $this->startMonth, $this->startDay, 0, 0))],
                ['<=', 'end_at', (int) Yii::$app->formatter->asTimestamp($this->prepareDate($this->endYear, $this->endMonth, $this->endDay, 23, 59))],
            ];

            if ($this->off->id !== null) {
                $conditions[] = ['<>', 'id', $this->off->id];
            }

            if (Off::find()->where($conditions)->exists()) {
                $this->addError('endDay', Yii::t('app', 'Selected day overlaps another off-time.'));
            }
        }
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'startYear' => Yii::t('app', 'Year'),
            'startMonth' => Yii::t('app', 'Month'),
            'startDay' => Yii::t('app', 'Day'),
            'endYear' => Yii::t('app', 'Year'),
            'endMonth' => Yii::t('app', 'Month'),
            'endDay' => Yii::t('app', 'Day'),
            'note' => Yii::t('app', 'Note'),
        ];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->off->user_id === null) {
            $this->off->user_id = Yii::$app->user->id;
        }

        $this->off->start_at = $this->prepareTimestamp($this->startYear, $this->startMonth, $this->startDay, 0, 0);
        $this->off->end_at = $this->prepareTimestamp($this->endYear, $this->endMonth, $this->endDay, 23, 59);
        $this->off->note = $this->note !== '' ? $this->note : null;

        return $this->off->save();
    }
}
