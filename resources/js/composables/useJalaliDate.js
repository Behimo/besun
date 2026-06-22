import moment from 'moment-jalaali'

moment.loadPersian({ dialect: 'persian-modern', usePersianDigits: true })

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const DATE_ONLY_RE = /^\d{4}-\d{2}-\d{2}$/
const ISO_DATETIME_RE = /^\d{4}-\d{2}-\d{2}T/

export const toLatinDigits = value => {
  if (value == null || value === '')
    return value

  return String(value).replace(/[۰-۹]/g, ch => String(PERSIAN_DIGITS.indexOf(ch)))
}

const parseInput = value => {
  if (value == null || value === '')
    return null

  if (moment.isMoment(value))
    return value.clone()

  if (value instanceof Date)
    return moment(value)

  const normalized = toLatinDigits(String(value).trim())

  if (DATE_ONLY_RE.test(normalized))
    return moment(normalized, 'YYYY-MM-DD', true)

  if (ISO_DATETIME_RE.test(normalized))
    return moment(normalized)

  const jalali = moment(normalized, 'jYYYY/jMM/jDD', true)
  if (jalali.isValid())
    return jalali

  const jalaliLoose = moment(normalized, 'jYYYY/jM/jD', true)
  if (jalaliLoose.isValid())
    return jalaliLoose

  const parsed = moment(normalized, ['YYYY-MM-DDTHH:mm:ss', 'YYYY-MM-DDTHH:mm'], true)
  if (parsed.isValid())
    return parsed

  return moment(normalized)
}

export const toApiDate = value => {
  const parsed = parseInput(value)

  return parsed?.isValid() ? parsed.clone().locale('en').format('YYYY-MM-DD') : null
}

export const toApiDateTime = (date, time = '00:00') => {
  if (!date)
    return null

  const normalizedDate = toApiDate(date)
  if (!normalizedDate)
    return null

  const rawTime = toLatinDigits(time)
  const timeMatch = rawTime.match(/^(\d{1,2}):(\d{2})/)

  if (!timeMatch)
    return null

  const normalizedTime = `${timeMatch[1].padStart(2, '0')}:${timeMatch[2]}`
  const parsed = moment(`${normalizedDate}T${normalizedTime}`, 'YYYY-MM-DDTHH:mm', true)

  if (!parsed.isValid())
    return null

  return parsed.locale('en').format('YYYY-MM-DDTHH:mm:ss')
}

export const useJalaliDate = () => {
  const formatDate = (value, format = 'jYYYY/jMM/jDD') => {
    if (!value)
      return ''

    const parsed = parseInput(value)

    return parsed?.isValid() ? parsed.format(format) : ''
  }

  const formatDateTime = value => formatDate(value, 'jYYYY/jMM/jDD HH:mm')

  const toGregorian = jalaliString => {
    if (!jalaliString)
      return null

    const m = moment(toLatinDigits(String(jalaliString).trim()), 'jYYYY/jMM/jDD', true)

    return m.isValid() ? m.locale('en').format('YYYY-MM-DD') : null
  }

  return {
    formatDate,
    formatDateTime,
    toGregorian,
    toApiDate,
    toApiDateTime,
    toLatinDigits,
    moment,
    parseInput,
  }
}
