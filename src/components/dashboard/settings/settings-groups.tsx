"use client";

import type { SettingsGroupItem } from "@/types";

interface SettingsGroupsNavProps {
  groups: SettingsGroupItem[];
  activeGroup: string;
  onSelect: (group: string) => void;
}

export function SettingsGroupsNav({
  groups,
  activeGroup,
  onSelect,
}: SettingsGroupsNavProps) {
  return (
    <nav className="h-fit space-y-1">
      {groups.map((group) => {
        const isActive = group.group === activeGroup;

        return (
          <button
            key={group.group}
            type="button"
            onClick={() => onSelect(group.group)}
            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors ${
              isActive
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:bg-muted hover:text-foreground"
            }`}
          >
            <span className="flex-1 truncate">{group.label}</span>
          </button>
        );
      })}
    </nav>
  );
}
