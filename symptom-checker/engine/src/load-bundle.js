/**
 * Bundle loading: one internal model, two on-disk shapes.
 *
 * Callers may hand in the schema 1.0.0 bundle (paths + external evidence map) or
 * the schema 2.0.0 bundle (disease × symptom matrix with the evidence inside the
 * options). Everything downstream — validation, derivation, strategies — only
 * ever sees the 1.0.0 shape, so a second format cannot quietly change what the
 * engine does.
 *
 *   const { format, bundle, evidenceMap } = loadBundle(raw, { evidenceMap });
 *
 * `format` is the schema the caller supplied; `bundle` is the normalised shape.
 */

import { bundleToEngineShape, evidenceMapFromBundle, isV2Bundle } from './adapter.js';
import { SUPPORTED_SCHEMA_VERSION, collectBundleIssues } from './validate-bundle.js';
import { validateEvidenceMap } from './evidence.js';
import { BundleFormatError, BundleValidationError, BundleVersionError } from './errors.js';

export const V2_SUPPORTED_SCHEMA_VERSION = '2.0.0';

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

/**
 * @param {object} input v1 or v2 bundle.
 * @param {{evidenceMap?: object|null}} options
 * @returns {{format: string, schemaVersion: string, bundle: object, evidenceMap: object|null, bundleId: string, bundleVersion: string}}
 */
export function loadBundle(input, { evidenceMap = null } = {}) {
  if (!isObject(input)) {
    throw new BundleFormatError(input === null ? 'null' : typeof input);
  }

  const shape = isV2Bundle(input) ? V2_SUPPORTED_SCHEMA_VERSION : String(input.schema_version ?? '');
  if (shape !== SUPPORTED_SCHEMA_VERSION && shape !== V2_SUPPORTED_SCHEMA_VERSION) {
    throw new BundleVersionError(input.schema_version, SUPPORTED_SCHEMA_VERSION);
  }

  const bundle = shape === V2_SUPPORTED_SCHEMA_VERSION ? bundleToEngineShape(input) : input;
  // v2 carries its evidence inside the options; a caller may still override it.
  const map = evidenceMap ?? (shape === V2_SUPPORTED_SCHEMA_VERSION ? evidenceMapFromBundle(input) : null);

  const issues = collectBundleIssues(bundle);
  if (issues.length) throw new BundleValidationError(issues);

  if (map) {
    const mapIssues = validateEvidenceMap(bundle, map);
    if (mapIssues.length) throw new BundleValidationError(mapIssues);
  }

  return {
    format: shape,
    schemaVersion: input.schema_version,
    bundle,
    evidenceMap: map,
    bundleId: input.bundle_id,
    bundleVersion: input.bundle_version,
  };
}
