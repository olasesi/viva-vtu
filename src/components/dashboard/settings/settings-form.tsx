"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { SettingFieldDefinition, SettingValue } from "@/types";

export type SettingsValues = Record<string, SettingValue>;

export function buildInitialValues(
  schemaFields: Record<string, SettingFieldDefinition>,
  fields: Record<string, SettingValue> | undefined,
): SettingsValues {
  const out: SettingsValues = {};

  for (const [key, definition] of Object.entries(schemaFields)) {
    const current = fields?.[key] ?? definition.default ?? null;
    out[key] =
      definition.type === "array" && Array.isArray(current)
        ? JSON.stringify(current)
        : current;
  }

  return out;
}

interface SettingsFormProps {
  schema: Record<string, SettingFieldDefinition>;
  values: SettingsValues;
  onChange: (key: string, value: SettingValue) => void;
}

export function SettingsForm({ schema, values, onChange }: SettingsFormProps) {
  return (
    <div className="space-y-6">
      {Object.entries(schema).map(([key, definition]) => (
        <Field
          key={key}
          fieldKey={key}
          definition={definition}
          value={values[key] ?? definition.default ?? null}
          onChange={onChange}
        />
      ))}
    </div>
  );
}

interface FieldProps {
  fieldKey: string;
  definition: SettingFieldDefinition;
  value: SettingValue;
  onChange: (key: string, value: SettingValue) => void;
}

function Field({ fieldKey, definition, value, onChange }: FieldProps) {
  const { type, label } = definition;

  const setValue = (next: SettingValue) => onChange(fieldKey, next);

  if (type === "boolean") {
    return (
      <div className="flex items-center justify-between gap-4">
        <Label htmlFor={`field-${fieldKey}`}>{label}</Label>
        <input
          id={`field-${fieldKey}`}
          type="checkbox"
          checked={value === true}
          onChange={(event) => setValue(event.target.checked)}
          className="h-4 w-4 rounded border-input accent-primary"
        />
      </div>
    );
  }

  if (type === "enum") {
    const options = definition.options ?? [];

    return (
      <div className="space-y-1.5">
        <Label htmlFor={`field-${fieldKey}`}>{label}</Label>
        <Select
          value={String(value ?? "")}
          onValueChange={(selected) => setValue(selected)}
        >
          <SelectTrigger id={`field-${fieldKey}`} className="w-full">
            <SelectValue placeholder="Select an option" />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option} value={option}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    );
  }

  if (type === "integer" || type === "decimal") {
    return (
      <div className="space-y-1.5">
        <Label htmlFor={`field-${fieldKey}`}>{label}</Label>
        <Input
          id={`field-${fieldKey}`}
          type="number"
          step={type === "decimal" ? "any" : "1"}
          value={value === null || value === "" ? "" : String(value)}
          onChange={(event) => {
            const raw = event.target.value;
            if (raw === "") {
              setValue("");
              return;
            }
            setValue(
              type === "decimal"
                ? Number.parseFloat(raw)
                : Number.parseInt(raw, 10),
            );
          }}
        />
      </div>
    );
  }

  if (type === "array") {
    return (
      <div className="space-y-1.5">
        <Label htmlFor={`field-${fieldKey}`}>{label}</Label>
        <Textarea
          id={`field-${fieldKey}`}
          rows={4}
          value={value === null || value === "" ? "" : String(value)}
          onChange={(event) => setValue(event.target.value)}
        />
      </div>
    );
  }

  return (
    <div className="space-y-1.5">
      <Label htmlFor={`field-${fieldKey}`}>{label}</Label>
      <Input
        id={`field-${fieldKey}`}
        type={definition.sensitive ? "password" : "text"}
        value={value === null ? "" : String(value)}
        onChange={(event) => setValue(event.target.value)}
      />
    </div>
  );
}
