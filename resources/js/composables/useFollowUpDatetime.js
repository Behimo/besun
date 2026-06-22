export const useFollowUpDatetime = () => {
  const mergeDatetime = (date, time = '09:00') => {
    if (!date)
      return null

    return `${date}T${time || '09:00'}:00`
  }

  const splitDatetime = iso => {
    if (!iso)
      return { date: '', time: '09:00' }

    const m = useJalaliDate().moment(iso)

    if (!m.isValid())
      return { date: '', time: '09:00' }

    return {
      date: m.format('YYYY-MM-DD'),
      time: m.format('HH:mm'),
    }
  }

  const followUpMeta = iso => {
    if (!iso)
      return null

    const { moment, formatDateTime } = useJalaliDate()
    const m = moment(iso)

    if (!m.isValid())
      return null

    const overdue = m.isBefore(moment())

    return {
      text: formatDateTime(iso),
      overdue,
      label: overdue ? 'پیگیری گذشته' : 'پیگیری',
    }
  }

  return {
    mergeDatetime,
    splitDatetime,
    followUpMeta,
  }
}
