export const useCrmBi = () => {
  const fetchDashboard = async ({ from, to, granularity } = {}) => {
    const query = {}

    if (from)
      query.from = from
    if (to)
      query.to = to
    if (granularity)
      query.granularity = granularity

    return await $api('/bi/dashboard', { query })
  }

  const fetchTemplates = async () => {
    const res = await $api('/bi/templates')

    return res.templates ?? []
  }

  const fetchReport = async ({ template, from, to, department, assignee_id } = {}) => {
    const query = { template }

    if (from)
      query.from = from
    if (to)
      query.to = to
    if (department)
      query.department = department
    if (assignee_id)
      query.assignee_id = assignee_id

    return await $api('/bi/reports', { query })
  }

  const exportTableCsv = (table, filename = 'report.csv') => {
    if (!table?.columns?.length || !table?.rows?.length)
      return

    const headers = table.columns.map(c => c.title)
    const keys = table.columns.map(c => c.key)
    const lines = [
      headers.join(','),
      ...table.rows.map(row =>
        keys.map(key => {
          const val = row[key] ?? ''
          const str = String(val).replace(/"/g, '""')

          return str.includes(',') ? `"${str}"` : str
        }).join(','),
      ),
    ]

    const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')

    link.href = URL.createObjectURL(blob)
    link.download = filename
    link.click()
    URL.revokeObjectURL(link.href)
  }

  return {
    fetchDashboard,
    fetchTemplates,
    fetchReport,
    exportTableCsv,
  }
}
