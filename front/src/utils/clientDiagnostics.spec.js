import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  clearDiagnosticEvents,
  CLIENT_DIAGNOSTIC_MAX_EVENTS,
  diagnosticEventCount,
  getDiagnosticEvents,
  trackDiagnostic,
} from './clientDiagnostics.js'

describe('clientDiagnostics', () => {
  beforeEach(() => {
    clearDiagnosticEvents()
  })

  it('acumula eventos e remove PII das data', () => {
    trackDiagnostic('foto.gallery.click', { email: 'x@y.z', size: 10, mime: 'image/jpeg' })
    const list = getDiagnosticEvents()
    assert.equal(list.length, 1)
    assert.equal(list[0].type, 'foto.gallery.click')
    assert.equal(list[0].data.size, 10)
    assert.equal(list[0].data.email, undefined)
    assert.equal(diagnosticEventCount(), 1)
  })

  it('respeita o limite máximo de eventos', () => {
    for (let i = 0; i < CLIENT_DIAGNOSTIC_MAX_EVENTS + 5; i += 1) {
      trackDiagnostic('ping', { i })
    }
    assert.equal(diagnosticEventCount(), CLIENT_DIAGNOSTIC_MAX_EVENTS)
    assert.equal(getDiagnosticEvents()[0].data.i, 5)
  })
})
