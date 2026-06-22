export const exportTableCsv = (filename, headers, rows) => {
  const escape = value => {
    const str = String(value ?? '')
    if (str.includes(',') || str.includes('"') || str.includes('\n'))
      return `"${str.replace(/"/g, '""')}"`

    return str
  }

  const lines = [
    headers.map(h => escape(h.label)).join(','),
    ...rows.map(row => headers.map(h => escape(row[h.key])).join(',')),
  ]

  const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export const formatRial = val => `${Number(val ?? 0).toLocaleString('fa-IR')} ریال`

export const healthColor = score => {
  if (score >= 80)
    return 'success'
  if (score >= 60)
    return 'info'
  if (score >= 40)
    return 'warning'

  return 'error'
}

export const ticketStatusLabel = status => ({
  open: 'باز',
  in_progress: 'در حال بررسی',
  resolved: 'حل‌شده',
  closed: 'بسته',
}[status] ?? status)

export const ticketPriorityLabel = priority => ({
  low: 'کم',
  medium: 'متوسط',
  high: 'بالا',
  urgent: 'فوری',
}[priority] ?? priority)

export const isPlatformStaffSession = userData => userData?.authType === 'platform_staff'

export const resolvePlatformPostLoginRoute = userData => {
  if (userData?.isPlatformSupport)
    return { name: 'apps-platform-support-dashboard' }

  if (userData?.isPlatformAdmin)
    return { name: 'apps-platform-dashboard' }

  return { name: 'admin-login' }
}

export const resolveCustomerPostLoginRoute = () => ({ name: 'dashboards-home' })

export const platformLoginRouteForPortal = portal =>
  portal === 'support' ? { name: 'support-login' } : { name: 'admin-login' }
