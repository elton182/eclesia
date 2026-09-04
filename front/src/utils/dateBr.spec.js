import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { brToIso, isoToBr, isValidBrDate, maskBrDateInput } from './dateBr.js'

describe('dateBr', () => {
  it('converte iso ↔ br', () => {
    assert.equal(isoToBr('2010-05-10'), '10/05/2010')
    assert.equal(brToIso('10/05/2010'), '2010-05-10')
    assert.equal(brToIso(''), null)
    assert.equal(isoToBr(''), '')
  })

  it('valida datas', () => {
    assert.equal(isValidBrDate('31/02/2020'), false)
    assert.equal(isValidBrDate('29/02/2020'), true)
    assert.equal(isValidBrDate('10/05/2010'), true)
  })

  it('mascara digitação', () => {
    assert.equal(maskBrDateInput('1005'), '10/05')
    assert.equal(maskBrDateInput('10052010'), '10/05/2010')
  })
})
